<?php

declare(strict_types=1);

final class ReportesController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $db = Database::connection();

        $porEstadoStmt = $db->prepare('SELECT estado, COUNT(*) AS total FROM tickets WHERE organizacion_id = :org GROUP BY estado');
        $porEstadoStmt->execute(['org' => $org]);
        $porEstado = $porEstadoStmt->fetchAll();

        $porPlantaStmt = $db->prepare(
            "SELECT p.nombre AS planta, COUNT(t.id) AS total
             FROM tickets t
             JOIN activos a ON a.id = t.activo_id
             JOIN plantas p ON p.id = a.planta_id
             WHERE t.organizacion_id = :org
             GROUP BY p.nombre"
        );
        $porPlantaStmt->execute(['org' => $org]);
        $porPlanta = $porPlantaStmt->fetchAll();

        $consumoStmt = $db->prepare(
            "SELECT pc.numero_parte, pc.descripcion, SUM(m.cantidad) AS total_consumido
             FROM movimientos_stock m
             JOIN productos_catalogo pc ON pc.id = m.producto_id
             WHERE m.tipo = 'salida' AND m.organizacion_id = :org
             GROUP BY pc.id
             ORDER BY total_consumido DESC
             LIMIT 10"
        );
        $consumoStmt->execute(['org' => $org]);
        $consumoRepuestos = $consumoStmt->fetchAll();

        // Costos: mano de obra registrada en las órdenes + repuestos consumidos a precio de lista.
        $manoObraStmt = $db->prepare(
            "SELECT COALESCE(SUM(costo_mano_obra), 0) FROM ordenes_trabajo WHERE organizacion_id = :org"
        );
        $manoObraStmt->execute(['org' => $org]);
        $costoManoObra = (float) $manoObraStmt->fetchColumn();

        $repuestosCostoStmt = $db->prepare(
            "SELECT COALESCE(SUM(m.cantidad * pc.precio_lista), 0)
             FROM movimientos_stock m JOIN productos_catalogo pc ON pc.id = m.producto_id
             WHERE m.tipo = 'salida' AND m.organizacion_id = :org"
        );
        $repuestosCostoStmt->execute(['org' => $org]);
        $costoRepuestosTotal = (float) $repuestosCostoStmt->fetchColumn();

        $downtimeStmt = $db->prepare(
            "SELECT a.nombre, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, d.inicio, COALESCE(d.fin, NOW()))), 0) / 60 AS horas
             FROM activos_downtime d JOIN activos a ON a.id = d.activo_id
             WHERE d.organizacion_id = :org
             GROUP BY a.id ORDER BY horas DESC LIMIT 10"
        );
        $downtimeStmt->execute(['org' => $org]);
        $downtimePorActivo = $downtimeStmt->fetchAll();

        require dirname(__DIR__, 2) . '/views/modulos/reportes/index.php';
    }

    public function exportTickets(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];

        $estado = trim($_GET['estado'] ?? '');
        $where = 't.organizacion_id = :org';
        $params = ['org' => $org];
        if ($estado !== '') {
            $where .= ' AND t.estado = :estado';
            $params['estado'] = $estado;
        }

        $stmt = Database::connection()->prepare(
            "SELECT t.folio, t.descripcion, t.categoria_final, t.prioridad_final, t.estado,
                    t.fecha_apertura, t.fecha_cierre, a.nombre AS activo
             FROM tickets t
             LEFT JOIN activos a ON a.id = t.activo_id
             WHERE {$where}
             ORDER BY t.fecha_apertura DESC"
        );
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tickets.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Folio', 'Descripción', 'Categoría', 'Prioridad', 'Estado', 'Apertura', 'Cierre', 'Activo'], ',', '"', '\\');
        foreach ($filas as $fila) {
            fputcsv($out, $fila, ',', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
