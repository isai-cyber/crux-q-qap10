<?php

declare(strict_types=1);

final class OrdenesController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare(
            "SELECT o.*, t.folio, t.descripcion AS ticket_descripcion, u.nombre AS tecnico_nombre
             FROM ordenes_trabajo o
             JOIN tickets t ON t.id = o.ticket_id
             LEFT JOIN usuarios u ON u.id = o.tecnico_id
             WHERE o.organizacion_id = :org
             ORDER BY o.created_at DESC"
        );
        $stmt->execute(['org' => $org]);
        $ordenes = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/ordenes/index.php';
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare(
            "SELECT o.*, t.folio, t.descripcion AS ticket_descripcion, t.activo_id
             FROM ordenes_trabajo o JOIN tickets t ON t.id = o.ticket_id
             WHERE o.id = :id AND o.organizacion_id = :org"
        );
        $stmt->execute(['id' => $id, 'org' => $org]);
        $orden = $stmt->fetch();
        if (!$orden) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            return;
        }

        $tecStmt = Database::connection()->prepare(
            "SELECT id, nombre FROM usuarios WHERE organizacion_id = :org AND rol = 'tecnico' AND activo = 1 ORDER BY nombre"
        );
        $tecStmt->execute(['org' => $org]);
        $tecnicos = $tecStmt->fetchAll();

        $runbookSugerido = $orden['runbook_id']
            ? null
            : RunbookModel::sugerirPara($org, $orden['ticket_descripcion'], $orden['activo_id'] ?: null);

        $runbooksDisponibles = RunbookModel::disponiblesParaSeleccion($org);

        $repuestosStmt = Database::connection()->prepare(
            'SELECT otr.cantidad, p.numero_parte, p.descripcion, p.precio_lista
             FROM orden_trabajo_repuesto otr JOIN productos_catalogo p ON p.id = otr.producto_id
             WHERE otr.orden_trabajo_id = :id'
        );
        $repuestosStmt->execute(['id' => $id]);
        $repuestosUsados = $repuestosStmt->fetchAll();
        $costoRepuestos = array_sum(array_map(
            fn ($r) => (int) $r['cantidad'] * (float) ($r['precio_lista'] ?? 0),
            $repuestosUsados
        ));

        require dirname(__DIR__, 2) . '/views/modulos/ordenes/show.php';
    }

    public function update(string $id): void
    {
        Auth::requireRole('admin', 'supervisor', 'tecnico');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $org = Auth::user()['organizacion_id'];

        // Verifica pertenencia a la organización y, de paso, si el runbook
        // elegido también es de esta organización antes de asociarlo.
        $ordenStmt = Database::connection()->prepare('SELECT id FROM ordenes_trabajo WHERE id = :id AND organizacion_id = :org');
        $ordenStmt->execute(['id' => $id, 'org' => $org]);
        if (!$ordenStmt->fetch()) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            return;
        }

        $runbookId = null;
        if (!empty($_POST['runbook_id'])) {
            $runbookId = RunbookModel::find($org, (int) $_POST['runbook_id']) ? (int) $_POST['runbook_id'] : null;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE ordenes_trabajo SET tecnico_id = :tecnico_id, runbook_id = :runbook_id, diagnostico = :diagnostico,
                solucion_aplicada = :solucion, solucion_exitosa = :solucion_exitosa,
                tiempo_invertido_minutos = :tiempo, costo_mano_obra = :costo_mano_obra, estado = :estado
             WHERE id = :id AND organizacion_id = :org'
        );
        $solucionExitosa = $_POST['solucion_exitosa'] ?? '';
        $stmt->execute([
            'id' => $id,
            'org' => $org,
            'tecnico_id' => $_POST['tecnico_id'] ?: null,
            'runbook_id' => $runbookId,
            'diagnostico' => $_POST['diagnostico'] ?? null,
            'solucion' => $_POST['solucion_aplicada'] ?? null,
            'solucion_exitosa' => $solucionExitosa === '' ? null : (int) $solucionExitosa,
            'tiempo' => $_POST['tiempo_invertido_minutos'] ?: null,
            'costo_mano_obra' => ($_POST['costo_mano_obra'] ?? '') !== '' ? (float) $_POST['costo_mano_obra'] : null,
            'estado' => $_POST['estado'] ?? 'pendiente',
        ]);

        if (($_POST['estado'] ?? '') === 'completada') {
            $this->cerrarYEvolucionar($org, (int) $id, $_POST);
        }

        header('Location: ' . base_url('/ordenes/' . $id));
        exit;
    }

    /**
     * Cierra el ticket y alimenta el ciclo de autoevolución:
     * - Si se aplicó un runbook existente, registra el resultado real
     *   (RunbookModel::registrarAplicacion se encarga de promover/retirar).
     * - Si no se usó un runbook pero sí hay una solución escrita, se crea
     *   un runbook nuevo "sugerido" a partir de este ticket resuelto.
     */
    private function cerrarYEvolucionar(int $org, int $ordenId, array $post): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, t.id AS ticket_id, t.descripcion AS ticket_descripcion, t.activo_id
             FROM ordenes_trabajo o JOIN tickets t ON t.id = o.ticket_id
             WHERE o.id = :id AND o.organizacion_id = :org'
        );
        $stmt->execute(['id' => $ordenId, 'org' => $org]);
        $orden = $stmt->fetch();
        if (!$orden) {
            return;
        }

        $runbookId = $orden['runbook_id'] ? (int) $orden['runbook_id'] : null;
        $solucionExitosa = $post['solucion_exitosa'] ?? '';

        if ($runbookId !== null && $solucionExitosa !== '') {
            RunbookModel::registrarAplicacion($org, $runbookId, (int) $orden['ticket_id'], $solucionExitosa === '1');
        } elseif ($runbookId === null && trim((string) ($orden['solucion_aplicada'] ?? '')) !== '') {
            RunbookModel::crearDesdeTicket(
                $org,
                ['descripcion' => $orden['ticket_descripcion'], 'activo_id' => $orden['activo_id']],
                $orden['solucion_aplicada'],
                $orden['diagnostico'] ?? null
            );
        }

        TicketModel::updateStatus($org, (int) $orden['ticket_id'], 'cerrado');
    }
}
