<?php

declare(strict_types=1);

final class ActivoModel
{
    public static function paginate(int $organizacionId, int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['a.organizacion_id = :org'];
        $params = ['org' => $organizacionId];

        if ($search !== null && $search !== '') {
            $where[] = '(a.nombre LIKE :search OR a.nombre_profinet LIKE :search OR a.ip_address LIKE :search)';
            $params['search'] = "%{$search}%";
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $db = Database::connection();

        $countStmt = $db->prepare("SELECT COUNT(*) FROM activos a {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT a.*, p.nombre AS planta_nombre
             FROM activos a
             JOIN plantas p ON p.id = a.planta_id
             {$whereSql}
             ORDER BY a.nombre
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
            'SELECT a.*, p.nombre AS planta_nombre FROM activos a JOIN plantas p ON p.id = a.planta_id
             WHERE a.id = :id AND a.organizacion_id = :org'
        );
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
        $activo = $stmt->fetch();
        return $activo ?: null;
    }

    public static function diagnosticos(int $activoId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM diagnosticos WHERE activo_id = :id ORDER BY fecha_diagnostico DESC'
        );
        $stmt->execute(['id' => $activoId]);
        return $stmt->fetchAll();
    }

    public static function create(int $organizacionId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO activos (organizacion_id, nombre, tipo, planta_id, ubicacion, ip_address, nombre_profinet, version_firmware, fecha_puesta_marcha, estado)
             VALUES (:org, :nombre, :tipo, :planta_id, :ubicacion, :ip_address, :nombre_profinet, :version_firmware, :fecha_puesta_marcha, :estado)'
        );
        $stmt->execute([
            'org' => $organizacionId,
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'] ?? null,
            'planta_id' => $data['planta_id'],
            'ubicacion' => $data['ubicacion'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'nombre_profinet' => $data['nombre_profinet'] ?? null,
            'version_firmware' => $data['version_firmware'] ?? null,
            'fecha_puesta_marcha' => !empty($data['fecha_puesta_marcha']) ? $data['fecha_puesta_marcha'] : null,
            'estado' => $data['estado'] ?? 'operativo',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $organizacionId, int $id, array $data): void
    {
        $anterior = self::find($organizacionId, $id);
        $nuevoEstado = $data['estado'] ?? 'operativo';

        $stmt = Database::connection()->prepare(
            'UPDATE activos SET nombre = :nombre, tipo = :tipo, planta_id = :planta_id, ubicacion = :ubicacion,
                ip_address = :ip_address, nombre_profinet = :nombre_profinet, version_firmware = :version_firmware,
                fecha_puesta_marcha = :fecha_puesta_marcha, estado = :estado
             WHERE id = :id AND organizacion_id = :org'
        );
        $stmt->execute([
            'id' => $id,
            'org' => $organizacionId,
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'] ?? null,
            'planta_id' => $data['planta_id'],
            'ubicacion' => $data['ubicacion'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'nombre_profinet' => $data['nombre_profinet'] ?? null,
            'version_firmware' => $data['version_firmware'] ?? null,
            'fecha_puesta_marcha' => !empty($data['fecha_puesta_marcha']) ? $data['fecha_puesta_marcha'] : null,
            'estado' => $nuevoEstado,
        ]);

        if ($anterior && $anterior['estado'] !== $nuevoEstado) {
            self::registrarCambioEstado($organizacionId, $id, $anterior['estado'], $nuevoEstado);
        }
    }

    /**
     * Lleva el historial de tiempo de inactividad (downtime) de un activo:
     * abre un registro cuando pasa a "fuera_servicio" y lo cierra cuando
     * sale de ese estado, para poder reportar horas de inactividad reales
     * (inspirado en Atlas CMMS).
     */
    private static function registrarCambioEstado(int $organizacionId, int $activoId, string $anterior, string $nuevo): void
    {
        $db = Database::connection();

        if ($nuevo === 'fuera_servicio' && $anterior !== 'fuera_servicio') {
            $db->prepare(
                'INSERT INTO activos_downtime (organizacion_id, activo_id, inicio) VALUES (:org, :activo, NOW())'
            )->execute(['org' => $organizacionId, 'activo' => $activoId]);
        } elseif ($anterior === 'fuera_servicio' && $nuevo !== 'fuera_servicio') {
            $db->prepare(
                'UPDATE activos_downtime SET fin = NOW()
                 WHERE activo_id = :activo AND organizacion_id = :org AND fin IS NULL
                 ORDER BY id DESC LIMIT 1'
            )->execute(['activo' => $activoId, 'org' => $organizacionId]);
        }
    }

    /** Horas de inactividad acumuladas por activo (cerradas + la abierta a la fecha, si aplica). */
    public static function downtimeHoras(int $organizacionId, int $activoId): float
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, inicio, COALESCE(fin, NOW()))), 0) AS minutos
             FROM activos_downtime WHERE organizacion_id = :org AND activo_id = :activo'
        );
        $stmt->execute(['org' => $organizacionId, 'activo' => $activoId]);
        return round(((float) $stmt->fetchColumn()) / 60, 1);
    }

    public static function delete(int $organizacionId, int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM activos WHERE id = :id AND organizacion_id = :org');
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
    }
}
