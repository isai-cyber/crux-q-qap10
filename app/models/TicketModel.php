<?php

declare(strict_types=1);

final class TicketModel
{
    public static function paginate(int $organizacionId, int $page = 1, int $perPage = 20, ?string $estado = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['t.organizacion_id = :org'];
        $params = ['org' => $organizacionId];
        if ($estado !== null && $estado !== '') {
            $where[] = 't.estado = :estado';
            $params['estado'] = $estado;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $db = Database::connection();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM tickets t {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT t.*, a.nombre AS activo_nombre
             FROM tickets t
             LEFT JOIN activos a ON a.id = t.activo_id
             {$whereSql}
             ORDER BY t.fecha_apertura DESC
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
            'SELECT t.*, a.nombre AS activo_nombre
             FROM tickets t
             LEFT JOIN activos a ON a.id = t.activo_id
             WHERE t.id = :id AND t.organizacion_id = :org'
        );
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
        $ticket = $stmt->fetch();
        return $ticket ?: null;
    }

    private static function nextFolio(int $organizacionId): string
    {
        $year = date('Y');
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM tickets WHERE organizacion_id = :org AND folio LIKE :prefix'
        );
        $stmt->execute(['org' => $organizacionId, 'prefix' => "TCK-{$year}-%"]);
        $count = (int) $stmt->fetchColumn() + 1;
        return sprintf('TCK-%s-%04d', $year, $count);
    }

    public static function create(int $organizacionId, array $data): array
    {
        $db = Database::connection();
        $folio = self::nextFolio($organizacionId);

        $stmt = $db->prepare(
            'INSERT INTO tickets (organizacion_id, folio, activo_id, reportado_por_id, canal_origen, tipo_mantenimiento, descripcion,
                categoria_ia, prioridad_ia, estado, fecha_apertura)
             VALUES (:org, :folio, :activo_id, :reportado_por_id, :canal_origen, :tipo_mantenimiento, :descripcion,
                :categoria_ia, :prioridad_ia, :estado, NOW())'
        );
        $stmt->execute([
            'org' => $organizacionId,
            'folio' => $folio,
            'activo_id' => $data['activo_id'] ?? null,
            'reportado_por_id' => $data['reportado_por_id'] ?? null,
            'canal_origen' => $data['canal_origen'] ?? 'web',
            'tipo_mantenimiento' => $data['tipo_mantenimiento'] ?? 'correctivo',
            'descripcion' => $data['descripcion'],
            'categoria_ia' => $data['categoria_ia'] ?? null,
            'prioridad_ia' => $data['prioridad_ia'] ?? null,
            'estado' => 'abierto',
        ]);

        $id = (int) $db->lastInsertId();

        $ordenStmt = $db->prepare(
            'INSERT INTO ordenes_trabajo (organizacion_id, ticket_id, estado) VALUES (:org, :ticket_id, :estado)'
        );
        $ordenStmt->execute(['org' => $organizacionId, 'ticket_id' => $id, 'estado' => 'pendiente']);

        return self::find($organizacionId, $id);
    }

    public static function updateStatus(int $organizacionId, int $id, string $estado, ?string $prioridadFinal = null, ?string $categoriaFinal = null): void
    {
        $db = Database::connection();
        $fechaCierre = $estado === 'cerrado' ? 'NOW()' : 'NULL';
        $stmt = $db->prepare(
            "UPDATE tickets SET estado = :estado, prioridad_final = :prioridad_final,
                categoria_final = :categoria_final, fecha_cierre = {$fechaCierre}
             WHERE id = :id AND organizacion_id = :org"
        );
        $stmt->execute([
            'id' => $id,
            'org' => $organizacionId,
            'estado' => $estado,
            'prioridad_final' => $prioridadFinal,
            'categoria_final' => $categoriaFinal,
        ]);
    }
}
