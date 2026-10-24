<?php

declare(strict_types=1);

/**
 * API REST consumida por agentes externos (p. ej. Hermes Agent, el servidor
 * MCP) como "herramientas". Autenticación por token Bearer (tabla
 * api_tokens), no por sesión de navegador. Cada token pertenece a una
 * organización y todas las operaciones quedan aisladas a esa organización.
 */
final class ApiController
{
    public function crearTicket(): void
    {
        try {
            $this->crearTicketInterno();
        } catch (ApiAuthException $e) {
            $this->json($e->status, ['error' => $e->getMessage()]);
        }
    }

    private function crearTicketInterno(): void
    {
        $token = ApiAuth::authenticate('crear_ticket');
        $org = (int) $token['organizacion_id'];
        $input = $this->jsonBody();

        $descripcion = trim($input['descripcion'] ?? '');
        if ($descripcion === '') {
            $this->json(422, ['error' => 'El campo descripcion es obligatorio.']);
            return;
        }

        $activoId = null;
        if (!empty($input['activo_id'])) {
            $activoId = ActivoModel::find($org, (int) $input['activo_id']) ? (int) $input['activo_id'] : null;
        } elseif (!empty($input['activo_nombre'])) {
            $stmt = Database::connection()->prepare('SELECT id FROM activos WHERE nombre = :nombre AND organizacion_id = :org LIMIT 1');
            $stmt->execute(['nombre' => $input['activo_nombre'], 'org' => $org]);
            $activoId = $stmt->fetchColumn() ?: null;
        }

        $triage = (new AIService($org))->triageTicket($descripcion);

        $ticket = TicketModel::create($org, [
            'activo_id' => $activoId,
            'reportado_por_id' => null,
            'canal_origen' => $input['canal_origen'] ?? 'api',
            'descripcion' => $descripcion,
            'categoria_ia' => $triage['categoria'] ?? null,
            'prioridad_ia' => $triage['prioridad'] ?? null,
        ]);

        $runbook = RunbookModel::sugerirPara($org, $descripcion, $activoId);

        $this->json(201, [
            'ticket' => $ticket,
            'triage' => $triage,
            'runbook_sugerido' => $runbook,
        ]);
    }

    public function consultarActivo(string $id): void
    {
        try {
            $this->consultarActivoInterno($id);
        } catch (ApiAuthException $e) {
            $this->json($e->status, ['error' => $e->getMessage()]);
        }
    }

    private function consultarActivoInterno(string $id): void
    {
        $token = ApiAuth::authenticate('consultar_activos');
        $org = (int) $token['organizacion_id'];
        $activo = ActivoModel::find($org, (int) $id);
        if (!$activo) {
            $this->json(404, ['error' => 'Activo no encontrado.']);
            return;
        }
        $activo['diagnosticos'] = ActivoModel::diagnosticos((int) $id);
        $this->json(200, $activo);
    }

    public function crearActivo(): void
    {
        try {
            $this->crearActivoInterno();
        } catch (ApiAuthException $e) {
            $this->json($e->status, ['error' => $e->getMessage()]);
        }
    }

    private function crearActivoInterno(): void
    {
        $token = ApiAuth::authenticate('crear_activo');
        $org = (int) $token['organizacion_id'];
        $input = $this->jsonBody();

        if (trim($input['nombre'] ?? '') === '' || empty($input['planta_id'])) {
            $this->json(422, ['error' => 'nombre y planta_id son obligatorios.']);
            return;
        }

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM plantas WHERE id = :id AND organizacion_id = :org');
        $stmt->execute(['id' => $input['planta_id'], 'org' => $org]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->json(422, ['error' => 'planta_id no pertenece a tu organización.']);
            return;
        }

        $id = ActivoModel::create($org, $input);
        $this->json(201, ActivoModel::find($org, $id));
    }

    /**
     * Crea un técnico o usuario de negocio (admin/supervisor/tecnico) desde
     * un agente externo. Refleja la misma capacidad disponible en el chat
     * interno (ver ChatController::intentarGestionUsuarios()).
     */
    public function crearUsuario(): void
    {
        try {
            $this->crearUsuarioInterno();
        } catch (ApiAuthException $e) {
            $this->json($e->status, ['error' => $e->getMessage()]);
        }
    }

    private function crearUsuarioInterno(): void
    {
        $token = ApiAuth::authenticate('crear_usuario');
        $org = (int) $token['organizacion_id'];
        $input = $this->jsonBody();

        $controller = new UsuariosController();
        $errors = $controller->validar($org, $input);
        if ($errors) {
            $this->json(422, ['errors' => $errors]);
            return;
        }

        $id = UsuarioModel::create($org, $input);
        $usuario = UsuarioModel::find($org, $id);
        unset($usuario['password_hash']);
        $this->json(201, $usuario);
    }

    public function buscarCatalogo(): void
    {
        try {
            $this->buscarCatalogoInterno();
        } catch (ApiAuthException $e) {
            $this->json($e->status, ['error' => $e->getMessage()]);
        }
    }

    private function buscarCatalogoInterno(): void
    {
        $token = ApiAuth::authenticate('consultar_catalogo');
        $org = (int) $token['organizacion_id'];
        $q = trim($_GET['q'] ?? '');
        $resultado = ProductoModel::paginate($org, 1, 20, $q !== '' ? $q : null);
        $this->json(200, $resultado['items']);
    }

    public function alertasPendientes(): void
    {
        try {
            $this->alertasPendientesInterno();
        } catch (ApiAuthException $e) {
            $this->json($e->status, ['error' => $e->getMessage()]);
        }
    }

    private function alertasPendientesInterno(): void
    {
        $token = ApiAuth::authenticate('consultar_alertas');
        $org = (int) $token['organizacion_id'];
        $db = Database::connection();

        $ticketsStmt = $db->prepare(
            "SELECT id, folio, descripcion, prioridad_ia, fecha_apertura
             FROM tickets
             WHERE organizacion_id = :org AND estado = 'abierto'
                AND (prioridad_final = 'critica' OR (prioridad_final IS NULL AND prioridad_ia = 'critica'))
             ORDER BY fecha_apertura ASC"
        );
        $ticketsStmt->execute(['org' => $org]);

        $stockStmt = $db->prepare(
            'SELECT p.id, p.numero_parte, p.descripcion
             FROM productos_catalogo p
             LEFT JOIN inventario i ON i.producto_id = p.id AND i.organizacion_id = :org
             WHERE COALESCE(i.stock_actual, 0) = 0'
        );
        $stockStmt->execute(['org' => $org]);

        $this->json(200, [
            'tickets_criticos_sin_atender' => $ticketsStmt->fetchAll(),
            'productos_sin_stock' => $stockStmt->fetchAll(),
        ]);
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        return is_array($data) ? $data : [];
    }

    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
