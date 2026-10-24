<?php

declare(strict_types=1);

/**
 * Conexiones a servidores MCP EXTERNOS (JSON-RPC 2.0) que ORION puede
 * consumir como cliente — distinto de McpController, que expone las
 * herramientas de ESTA app a agentes externos.
 */
final class McpConexionModel
{
    public static function all(int $organizacionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM mcp_conexiones WHERE organizacion_id = :org AND activo = 1 ORDER BY nombre'
        );
        $stmt->execute(['org' => $organizacionId]);
        return $stmt->fetchAll();
    }

    public static function find(int $organizacionId, string $nombre): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM mcp_conexiones WHERE organizacion_id = :org AND nombre = :nombre AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['org' => $organizacionId, 'nombre' => $nombre]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public static function existe(int $organizacionId, string $nombre): bool
    {
        return self::find($organizacionId, $nombre) !== null;
    }

    public static function crear(int $organizacionId, string $nombre, string $url, ?string $token, array $herramientas, ?int $creadoPorId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO mcp_conexiones (organizacion_id, nombre, url, token, herramientas_cache, creado_por_id)
             VALUES (:org, :nombre, :url, :token, :herramientas, :creado_por)
             ON DUPLICATE KEY UPDATE url = VALUES(url), token = VALUES(token),
                 herramientas_cache = VALUES(herramientas_cache), activo = 1'
        );
        $stmt->execute([
            'org' => $organizacionId,
            'nombre' => $nombre,
            'url' => $url,
            'token' => $token,
            'herramientas' => json_encode($herramientas, JSON_UNESCAPED_UNICODE),
            'creado_por' => $creadoPorId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $organizacionId, string $nombre): bool
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM mcp_conexiones WHERE organizacion_id = :org AND nombre = :nombre'
        );
        return $stmt->execute(['org' => $organizacionId, 'nombre' => $nombre]);
    }

    /** Todas las herramientas de todas las conexiones activas, con el nombre de la conexión dueña. */
    public static function herramientasDisponibles(int $organizacionId): array
    {
        $herramientas = [];
        foreach (self::all($organizacionId) as $conexion) {
            $lista = json_decode($conexion['herramientas_cache'] ?? '[]', true) ?: [];
            foreach ($lista as $herramienta) {
                $herramientas[] = [
                    'conexion' => $conexion['nombre'],
                    'url' => $conexion['url'],
                    'token' => $conexion['token'],
                    'nombre' => $herramienta['name'] ?? '',
                    'descripcion' => $herramienta['description'] ?? '',
                    'inputSchema' => $herramienta['inputSchema'] ?? ['type' => 'object', 'properties' => []],
                ];
            }
        }
        return $herramientas;
    }
}
