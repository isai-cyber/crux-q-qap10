<?php

declare(strict_types=1);

/**
 * Motor de autoevolución de runbooks (por organización): cada aplicación
 * real a un ticket (éxito/fallo) ajusta contadores, y el runbook se
 * promueve o se retira solo, sin intervención humana. Reglas (ver
 * docs/ANALISIS.md §autoevolución):
 *  - Se valida (validado=1) al alcanzar 3 éxitos con tasa de éxito >= 70%.
 *  - Se retira (vigente=0) al alcanzar 3 aplicaciones con tasa de fallo >= 50%.
 */
final class RunbookModel
{
    private const MIN_APLICACIONES_PARA_DECIDIR = 3;
    private const UMBRAL_VALIDACION = 0.7;
    private const UMBRAL_RETIRO = 0.5;

    public static function all(int $organizacionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, a.nombre AS activo_nombre FROM runbooks r
             LEFT JOIN activos a ON a.id = r.activo_id
             WHERE r.organizacion_id = :org
             ORDER BY r.validado DESC, r.veces_exitoso DESC, r.created_at DESC'
        );
        $stmt->execute(['org' => $organizacionId]);
        return $stmt->fetchAll();
    }

    public static function find(int $organizacionId, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM runbooks WHERE id = :id AND organizacion_id = :org');
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
        $runbook = $stmt->fetch();
        return $runbook ?: null;
    }

    public static function disponiblesParaSeleccion(int $organizacionId, int $limite = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, titulo FROM runbooks WHERE organizacion_id = :org AND vigente = 1
             ORDER BY validado DESC, veces_exitoso DESC LIMIT :limite'
        );
        $stmt->bindValue(':org', $organizacionId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Busca el mejor runbook vigente (de esta organización) para un texto
     * de descripción de falla, usando coincidencia simple de palabras clave
     * contra el síntoma (heurística de respaldo; si hay IA configurada, se
     * puede sustituir por similitud de embeddings sin cambiar la interfaz).
     */
    public static function sugerirPara(int $organizacionId, string $descripcion, ?int $activoId = null): ?array
    {
        $palabras = array_filter(preg_split('/\s+/', mb_strtolower($descripcion)), fn ($w) => mb_strlen($w) >= 4);
        if (!$palabras) {
            return null;
        }

        $condiciones = [];
        $params = ['org' => $organizacionId];
        foreach (array_values($palabras) as $i => $palabra) {
            $condiciones[] = "r.sintoma LIKE :p{$i}";
            $params["p{$i}"] = "%{$palabra}%";
        }

        $activoSql = '';
        if ($activoId !== null) {
            $activoSql = ' AND (r.activo_id = :activo_id OR r.activo_id IS NULL)';
            $params['activo_id'] = $activoId;
        }

        $sql = 'SELECT r.* FROM runbooks r
                WHERE r.organizacion_id = :org AND r.vigente = 1 AND (' . implode(' OR ', $condiciones) . ')' . $activoSql . '
                ORDER BY r.validado DESC, r.veces_exitoso DESC
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $runbook = $stmt->fetch();
        return $runbook ?: null;
    }

    public static function crearDesdeTicket(int $organizacionId, array $ticket, string $solucion, ?string $causa = null): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO runbooks (organizacion_id, titulo, sintoma, causa, solucion, activo_id, generado_por, validado, vigente)
             VALUES (:org, :titulo, :sintoma, :causa, :solucion, :activo_id, :generado_por, 0, 1)'
        );
        $stmt->execute([
            'org' => $organizacionId,
            'titulo' => mb_strimwidth($ticket['descripcion'], 0, 100, '…'),
            'sintoma' => $ticket['descripcion'],
            'causa' => $causa,
            'solucion' => $solucion,
            'activo_id' => $ticket['activo_id'] ?: null,
            'generado_por' => 'ia',
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Registra el resultado real de aplicar un runbook a un ticket y
     * ejecuta las reglas de promoción/retiro automático. $organizacionId se
     * usa para verificar que el runbook pertenece a esa organización antes
     * de tocarlo (evita que un ticket de otra organización lo afecte).
     */
    public static function registrarAplicacion(int $organizacionId, int $runbookId, int $ticketId, bool $exitoso): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id FROM runbooks WHERE id = :id AND organizacion_id = :org FOR UPDATE');
            $stmt->execute(['id' => $runbookId, 'org' => $organizacionId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('El runbook no pertenece a esta organización.');
            }

            $insert = $db->prepare(
                'INSERT INTO runbook_aplicaciones (runbook_id, ticket_id, resultado) VALUES (:runbook_id, :ticket_id, :resultado)'
            );
            $insert->execute([
                'runbook_id' => $runbookId,
                'ticket_id' => $ticketId,
                'resultado' => $exitoso ? 'exitoso' : 'fallido',
            ]);

            $campo = $exitoso ? 'veces_exitoso' : 'veces_fallido';
            $db->prepare(
                "UPDATE runbooks SET veces_aplicado = veces_aplicado + 1, {$campo} = {$campo} + 1 WHERE id = :id"
            )->execute(['id' => $runbookId]);

            $stmt = $db->prepare('SELECT veces_aplicado, veces_exitoso, veces_fallido FROM runbooks WHERE id = :id');
            $stmt->execute(['id' => $runbookId]);
            $contadores = $stmt->fetch();

            self::evaluarPromocionORetiro($runbookId, $contadores);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function evaluarPromocionORetiro(int $runbookId, array $contadores): void
    {
        $total = (int) $contadores['veces_aplicado'];
        if ($total < self::MIN_APLICACIONES_PARA_DECIDIR) {
            return;
        }

        $tasaExito = $contadores['veces_exitoso'] / $total;
        $tasaFallo = $contadores['veces_fallido'] / $total;

        $db = Database::connection();

        if ($tasaFallo >= self::UMBRAL_RETIRO) {
            $db->prepare('UPDATE runbooks SET vigente = 0 WHERE id = :id')->execute(['id' => $runbookId]);
            return;
        }

        if ($tasaExito >= self::UMBRAL_VALIDACION) {
            $db->prepare('UPDATE runbooks SET validado = 1 WHERE id = :id')->execute(['id' => $runbookId]);
        }
    }
}
