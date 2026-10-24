<?php

declare(strict_types=1);

/**
 * Excepción que ApiAuth lanza al rechazar una petición. Los controladores
 * JSON (API REST / MCP) la capturan para devolver el error en su formato
 * propio (JSON-RPC -32000, envelope uniforme, etc.) en lugar del exit con
 * status HTTP crudo del deny() legado.
 */
final class ApiAuthException extends RuntimeException
{
    public int $status;

    public function __construct(string $message, int $status)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

final class ApiAuth
{
    public static function authenticate(string ...$permisosRequeridos): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            self::deny(401, 'Falta el token de autorización.');
        }

        $tokenPlano = $matches[1];
        $hash = hash('sha256', $tokenPlano);

        $stmt = Database::connection()->prepare(
            'SELECT * FROM api_tokens WHERE token_hash = :hash AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $token = $stmt->fetch();

        if (!$token) {
            self::deny(401, 'Token inválido o revocado.');
        }

        $permisos = json_decode($token['permisos'], true) ?: [];
        foreach ($permisosRequeridos as $permiso) {
            if (!in_array($permiso, $permisos, true)) {
                self::deny(403, "El token no tiene el permiso '{$permiso}'.");
            }
        }

        $update = Database::connection()->prepare('UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = :id');
        $update->execute(['id' => $token['id']]);

        return $token;
    }

    private static function deny(int $status, string $message): void
    {
        throw new ApiAuthException($message, $status);
    }
}
