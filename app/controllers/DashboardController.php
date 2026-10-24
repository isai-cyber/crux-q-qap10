<?php

declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];

        $datos = $this->datos((int) $org);
        $kpis = $datos['kpis'];
        $ticketsRecientes = $datos['tickets_recientes'];
        $porEstado = $datos['tickets_por_estado'];
        $ticketsPorDia = $datos['tickets_por_dia'];
        $ticketsPorPrioridad = $datos['tickets_por_prioridad'];
        $tasaCierre = $datos['tasa_cierre'];
        $totalTickets = $datos['total_tickets'];

        require dirname(__DIR__, 2) . '/views/dashboard/index.php';
    }

    /**
     * KPIs, últimos tickets y distribuciones analíticas de una organización.
     * Fuente única compartida por el dashboard web y la API móvil
     * (AuthApiController::dashboard) para evitar SQL duplicado.
     *
     * @return array{kpis: array, tickets_recientes: array, tickets_por_estado: array, tickets_por_dia: array, tickets_por_prioridad: array, tasa_cierre: float, total_tickets: int}
     */
    public function datos(int $org): array
    {
        $db = Database::connection();

        $kpis = [
            'tickets_abiertos' => (int) $this->contar($db,
                "SELECT COUNT(*) FROM tickets WHERE organizacion_id = :org AND estado IN ('abierto','en_progreso','esperando_repuesto')", $org
            ),
            'tickets_criticos' => (int) $this->contar($db,
                "SELECT COUNT(*) FROM tickets WHERE organizacion_id = :org AND estado <> 'cerrado' AND (prioridad_final = 'critica' OR (prioridad_final IS NULL AND prioridad_ia = 'critica'))", $org
            ),
            'activos_fuera_servicio' => (int) $this->contar($db,
                "SELECT COUNT(*) FROM activos WHERE organizacion_id = :org AND estado = 'fuera_servicio'", $org
            ),
            'productos_sin_stock' => (int) $this->contar($db,
                'SELECT COUNT(*) FROM productos_catalogo p
                 LEFT JOIN inventario i ON i.producto_id = p.id AND i.organizacion_id = :org
                 WHERE COALESCE(i.stock_actual, 0) = 0', $org
            ),
        ];

        $stmt = $db->prepare(
            "SELECT t.*, a.nombre AS activo_nombre FROM tickets t
             LEFT JOIN activos a ON a.id = t.activo_id
             WHERE t.organizacion_id = :org
             ORDER BY t.fecha_apertura DESC LIMIT 8"
        );
        $stmt->execute(['org' => $org]);
        $ticketsRecientes = $stmt->fetchAll();

        $estadoStmt = $db->prepare(
            'SELECT estado, COUNT(*) AS total FROM tickets
             WHERE organizacion_id = :org GROUP BY estado'
        );
        $estadoStmt->execute(['org' => $org]);
        $porEstado = $estadoStmt->fetchAll();

        // Tickets por día (últimos 14 días, rellenando días sin actividad).
        $porDiaStmt = $db->prepare(
            "SELECT DATE(fecha_apertura) AS dia, COUNT(*) AS total
             FROM tickets
             WHERE organizacion_id = :org AND fecha_apertura >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(fecha_apertura)"
        );
        $porDiaStmt->execute(['org' => $org]);
        $porDiaMap = [];
        foreach ($porDiaStmt->fetchAll() as $fila) {
            $porDiaMap[$fila['dia']] = (int) $fila['total'];
        }
        $porDia = [];
        for ($i = 13; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-$i days"));
            $porDia[] = ['dia' => $dia, 'total' => $porDiaMap[$dia] ?? 0];
        }

        // Distribución por prioridad (prioridad_final si existe, si no la IA).
        $prioridadStmt = $db->prepare(
            "SELECT COALESCE(prioridad_final, prioridad_ia) AS prioridad, COUNT(*) AS total
             FROM tickets
             WHERE organizacion_id = :org
             GROUP BY COALESCE(prioridad_final, prioridad_ia)"
        );
        $prioridadStmt->execute(['org' => $org]);
        $porPrioridadMap = ['critica' => 0, 'alta' => 0, 'media' => 0, 'baja' => 0];
        foreach ($prioridadStmt->fetchAll() as $fila) {
            if ($fila['prioridad'] !== null && array_key_exists($fila['prioridad'], $porPrioridadMap)) {
                $porPrioridadMap[$fila['prioridad']] = (int) $fila['total'];
            }
        }

        $totalTickets = array_sum($porPrioridadMap);
        $cerrados = 0;
        foreach ($porEstado as $fila) {
            if ($fila['estado'] === 'cerrado') {
                $cerrados = (int) $fila['total'];
            }
        }
        $tasaCierre = $totalTickets > 0 ? round($cerrados / $totalTickets * 100, 1) : 0.0;

        return [
            'kpis' => $kpis,
            'tickets_recientes' => $ticketsRecientes,
            'tickets_por_estado' => $porEstado,
            'tickets_por_dia' => $porDia,
            'tickets_por_prioridad' => $porPrioridadMap,
            'tasa_cierre' => $tasaCierre,
            'total_tickets' => $totalTickets,
        ];
    }

    private function contar(PDO $db, string $sql, int $org): int
    {
        $stmt = $db->prepare($sql);
        $stmt->execute(['org' => $org]);
        return (int) $stmt->fetchColumn();
    }
}
