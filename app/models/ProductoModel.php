<?php

declare(strict_types=1);

/**
 * El catálogo (productos_catalogo/categorias_producto) es GLOBAL y se
 * comparte entre todas las organizaciones (datos de referencia del
 * fabricante). El stock ("inventario") es propio de cada organización.
 */
final class ProductoModel
{
    public static function paginate(int $organizacionId, int $page = 1, int $perPage = 20, ?string $search = null, ?int $categoriaId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = ['org' => $organizacionId];

        if ($search !== null && $search !== '') {
            $where[] = '(p.numero_parte LIKE :search1 OR p.descripcion LIKE :search2)';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }
        if ($categoriaId !== null) {
            $where[] = 'p.categoria_id = :categoria_id';
            $params['categoria_id'] = $categoriaId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $db = Database::connection();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM productos_catalogo p {$whereSql}");
        $countParams = $params;
        unset($countParams['org']);
        $countStmt->execute($countParams);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT p.*, c.nombre AS categoria_nombre, COALESCE(i.stock_actual, 0) AS stock_actual
             FROM productos_catalogo p
             LEFT JOIN categorias_producto c ON c.id = p.categoria_id
             LEFT JOIN inventario i ON i.producto_id = p.id AND i.organizacion_id = :org
             {$whereSql}
             ORDER BY p.numero_parte
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function find(int $organizacionId, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, c.nombre AS categoria_nombre, COALESCE(i.stock_actual, 0) AS stock_actual
             FROM productos_catalogo p
             LEFT JOIN categorias_producto c ON c.id = p.categoria_id
             LEFT JOIN inventario i ON i.producto_id = p.id AND i.organizacion_id = :org
             WHERE p.id = :id'
        );
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
        $producto = $stmt->fetch();
        return $producto ?: null;
    }

    public static function categorias(): array
    {
        return Database::connection()
            ->query('SELECT * FROM categorias_producto ORDER BY nombre')
            ->fetchAll();
    }

    public static function registrarMovimientoStock(int $organizacionId, int $productoId, string $tipo, int $cantidad, ?int $ordenTrabajoId, ?int $usuarioId, ?string $motivo = null): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO inventario (organizacion_id, producto_id, stock_actual) VALUES (:org, :producto_id, 0)
                 ON DUPLICATE KEY UPDATE stock_actual = stock_actual'
            )->execute(['org' => $organizacionId, 'producto_id' => $productoId]);

            $stmt = $db->prepare(
                'SELECT stock_actual FROM inventario WHERE organizacion_id = :org AND producto_id = :producto_id FOR UPDATE'
            );
            $stmt->execute(['org' => $organizacionId, 'producto_id' => $productoId]);
            $stockActual = (int) $stmt->fetchColumn();

            $nuevoStock = $tipo === 'entrada' ? $stockActual + $cantidad : $stockActual - $cantidad;
            if ($nuevoStock < 0) {
                throw new RuntimeException('No hay stock suficiente para esta salida.');
            }

            $db->prepare(
                'UPDATE inventario SET stock_actual = :stock WHERE organizacion_id = :org AND producto_id = :producto_id'
            )->execute(['stock' => $nuevoStock, 'org' => $organizacionId, 'producto_id' => $productoId]);

            $db->prepare(
                'INSERT INTO movimientos_stock (organizacion_id, producto_id, tipo, cantidad, orden_trabajo_id, motivo, usuario_id)
                 VALUES (:org, :producto_id, :tipo, :cantidad, :orden_trabajo_id, :motivo, :usuario_id)'
            )->execute([
                'org' => $organizacionId,
                'producto_id' => $productoId,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'orden_trabajo_id' => $ordenTrabajoId,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
