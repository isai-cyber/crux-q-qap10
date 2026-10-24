<?php

declare(strict_types=1);

/**
 * Mantenimiento preventivo programado: tareas recurrentes por calendario
 * (cada N días) sobre un activo. Cuando se cumple la fecha, se genera
 * automáticamente un ticket + orden de trabajo (tipo_mantenimiento =
 * 'preventivo'), reutilizando el mismo flujo que un reporte de falla.
 * Inspirado en la funcionalidad de Atlas CMMS, adaptado a este stack.
 */
final class PlanMantenimientoModel
{
    public static function all(int $organizacionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, a.nombre AS activo_nombre
             FROM planes_mantenimiento p
             JOIN activos a ON a.id = p.activo_id
             WHERE p.organizacion_id = :org AND p.activo = 1
             ORDER BY p.proxima_fecha ASC'
        );
        $stmt->execute(['org' => $organizacionId]);
        return $stmt->fetchAll();
    }

    public static function find(int $organizacionId, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM planes_mantenimiento WHERE id = :id AND organizacion_id = :org LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public static function create(int $organizacionId, array $data, ?int $creadoPorId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO planes_mantenimiento (organizacion_id, activo_id, titulo, descripcion, frecuencia_dias, proxima_fecha, creado_por_id)
             VALUES (:org, :activo_id, :titulo, :descripcion, :frecuencia, :proxima, :creado_por)'
        );
        $stmt->execute([
            'org' => $organizacionId,
            'activo_id' => $data['activo_id'],
            'titulo' => $data['titulo'],
            'descripcion' => $data['descripcion'] ?: null,
            'frecuencia' => $data['frecuencia_dias'],
            'proxima' => $data['proxima_fecha'],
            'creado_por' => $creadoPorId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function eliminar(int $organizacionId, int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE planes_mantenimiento SET activo = 0 WHERE id = :id AND organizacion_id = :org'
        );
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
    }

    /**
     * Revisa los planes vencidos (proxima_fecha <= hoy) de una organización
     * y genera un ticket preventivo por cada uno, reprogramando la próxima
     * fecha sumando frecuencia_dias. Idempotente por día: si ya se generó
     * hoy, no repite. Se llama de dos formas: perezosamente al cargar el
     * dashboard (para que funcione sin cron configurado) y desde
     * scripts/generar_preventivos.php (para instalaciones con cron).
     *
     * @return array Tickets generados en esta corrida.
     */
    public static function generarPendientes(int $organizacionId): array
    {
        $hoy = date('Y-m-d');
        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT p.*, a.nombre AS activo_nombre
             FROM planes_mantenimiento p
             JOIN activos a ON a.id = p.activo_id
             WHERE p.organizacion_id = :org AND p.activo = 1
               AND p.proxima_fecha <= :hoy
               AND (p.ultima_generacion IS NULL OR p.ultima_generacion < :hoy2)'
        );
        $stmt->execute(['org' => $organizacionId, 'hoy' => $hoy, 'hoy2' => $hoy]);
        $vencidos = $stmt->fetchAll();

        $generados = [];
        foreach ($vencidos as $plan) {
            $descripcion = 'Mantenimiento preventivo programado: ' . $plan['titulo']
                . ($plan['descripcion'] ? ' — ' . $plan['descripcion'] : '')
                . ' (activo: ' . $plan['activo_nombre'] . ').';

            $ticket = TicketModel::create($organizacionId, [
                'activo_id' => (int) $plan['activo_id'],
                'canal_origen' => 'sistema',
                'tipo_mantenimiento' => 'preventivo',
                'descripcion' => $descripcion,
                'categoria_ia' => 'Mantenimiento preventivo',
                'prioridad_ia' => 'media',
            ]);
            $generados[] = $ticket;

            $siguiente = date('Y-m-d', strtotime($plan['proxima_fecha'] . ' + ' . (int) $plan['frecuencia_dias'] . ' days'));
            $upd = $db->prepare(
                'UPDATE planes_mantenimiento SET proxima_fecha = :siguiente, ultima_generacion = :hoy WHERE id = :id'
            );
            $upd->execute(['siguiente' => $siguiente, 'hoy' => $hoy, 'id' => $plan['id']]);
        }

        return $generados;
    }
}
