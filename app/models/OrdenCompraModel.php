<?php

declare(strict_types=1);

/**
 * Reabastecimiento de inventario con aprobación (inspirado en Atlas CMMS,
 * adaptado a este stack): solicitar → aprobar/rechazar → recibir. Solo al
 * recibir se impacta el stock real (vía ProductoModel::registrarMovimientoStock).
 */
final class OrdenCompraModel
{
    public static function all(int $organizacionId, ?string $estado = null): array
    {
        $where = 'oc.organizacion_id = :org';
        $params = ['org' => $organizacionId];
        if ($estado !== null && $estado !== '') {
            $where .= ' AND oc.estado = :estado';
            $params['estado'] = $estado;
        }

        $stmt = Database::connection()->prepare(
            "SELECT oc.*, p.numero_parte, p.descripcion AS producto_descripcion,
                    us.nombre AS solicitante_nombre, ua.nombre AS aprobador_nombre
             FROM ordenes_compra oc
             JOIN productos_catalogo p ON p.id = oc.producto_id
             LEFT JOIN usuarios us ON us.id = oc.solicitado_por_id
             LEFT JOIN usuarios ua ON ua.id = oc.aprobado_por_id
             WHERE {$where}
             ORDER BY oc.fecha_solicitud DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $organizacionId, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT oc.*, p.numero_parte, p.descripcion AS producto_descripcion
             FROM ordenes_compra oc
             JOIN productos_catalogo p ON p.id = oc.producto_id
             WHERE oc.id = :id AND oc.organizacion_id = :org LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    private static function nextFolio(int $organizacionId): string
    {
        $year = date('Y');
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM ordenes_compra WHERE organizacion_id = :org AND folio LIKE :prefix'
        );
        $stmt->execute(['org' => $organizacionId, 'prefix' => "OC-{$year}-%"]);
        $count = (int) $stmt->fetchColumn() + 1;
        return sprintf('OC-%s-%04d', $year, $count);
    }

    public static function crear(int $organizacionId, array $data, ?int $solicitanteId): int
    {
        $folio = self::nextFolio($organizacionId);
        $stmt = Database::connection()->prepare(
            'INSERT INTO ordenes_compra (organizacion_id, folio, producto_id, cantidad, notas, solicitado_por_id)
             VALUES (:org, :folio, :producto_id, :cantidad, :notas, :solicitante)'
        );
        $stmt->execute([
            'org' => $organizacionId,
            'folio' => $folio,
            'producto_id' => $data['producto_id'],
            'cantidad' => $data['cantidad'],
            'notas' => $data['notas'] ?: null,
            'solicitante' => $solicitanteId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function aprobar(int $organizacionId, int $id, int $aprobadorId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE ordenes_compra SET estado = 'aprobada', aprobado_por_id = :aprobador, fecha_resolucion = NOW()
             WHERE id = :id AND organizacion_id = :org AND estado = 'pendiente'"
        );
        $stmt->execute(['aprobador' => $aprobadorId, 'id' => $id, 'org' => $organizacionId]);
        return $stmt->rowCount() > 0;
    }

    public static function rechazar(int $organizacionId, int $id, int $aprobadorId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE ordenes_compra SET estado = 'rechazada', aprobado_por_id = :aprobador, fecha_resolucion = NOW()
             WHERE id = :id AND organizacion_id = :org AND estado = 'pendiente'"
        );
        $stmt->execute(['aprobador' => $aprobadorId, 'id' => $id, 'org' => $organizacionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Marca la orden como recibida y AHÍ SÍ impacta el inventario real
     * (entrada de stock por la cantidad ordenada). Solo se puede recibir
     * una orden ya aprobada, y solo una vez.
     */
    public static function recibir(int $organizacionId, int $id, int $usuarioId): bool
    {
        $orden = self::find($organizacionId, $id);
        if (!$orden || $orden['estado'] !== 'aprobada') {
            return false;
        }

        // registrarMovimientoStock() maneja su propia transacción (PDO no
        // soporta transacciones anidadas), así que no se envuelve aquí.
        ProductoModel::registrarMovimientoStock(
            $organizacionId,
            (int) $orden['producto_id'],
            'entrada',
            (int) $orden['cantidad'],
            null,
            $usuarioId,
            'Recepción de orden de compra ' . $orden['folio']
        );

        $stmt = Database::connection()->prepare(
            "UPDATE ordenes_compra SET estado = 'recibida' WHERE id = :id AND organizacion_id = :org AND estado = 'aprobada'"
        );
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);

        return true;
    }
}
