<?php

declare(strict_types=1);

final class UsuarioModel
{
    /**
     * Devuelve todos los usuarios activos que coincidan con el nombre de
     * usuario, sin filtrar por organización (el login no sabe a qué
     * organización pertenece el usuario hasta verificar la contraseña). En
     * la práctica casi siempre habrá 0 o 1 coincidencias.
     */
    public static function findActiveByUsername(string $usuario): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM usuarios WHERE usuario = :usuario AND activo = 1'
        );
        $stmt->execute(['usuario' => $usuario]);
        return $stmt->fetchAll();
    }

    public static function find(int $organizacionId, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM usuarios WHERE id = :id AND organizacion_id = :org LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'org' => $organizacionId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function all(int $organizacionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nombre, usuario, email, rol, planta_id, activo FROM usuarios WHERE organizacion_id = :org ORDER BY nombre'
        );
        $stmt->execute(['org' => $organizacionId]);
        return $stmt->fetchAll();
    }

    /**
     * Crea un usuario. Si no se indica 'usuario' (caso de ORION/API/MCP,
     * que solo piden nombre+email+rol), se deriva uno a partir del email.
     */
    public static function create(int $organizacionId, array $data): int
    {
        $usuario = trim($data['usuario'] ?? '');
        if ($usuario === '') {
            $usuario = self::generarUsuarioDesdeEmail($organizacionId, $data['email']);
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO usuarios (organizacion_id, nombre, usuario, email, password_hash, rol, planta_id, activo)
             VALUES (:org, :nombre, :usuario, :email, :password_hash, :rol, :planta_id, 1)'
        );
        $stmt->execute([
            'org' => $organizacionId,
            'nombre' => $data['nombre'],
            'usuario' => $usuario,
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'rol' => $data['rol'],
            'planta_id' => $data['planta_id'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function existeEmailEnOrg(int $organizacionId, string $email): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM usuarios WHERE organizacion_id = :org AND email = :email'
        );
        $stmt->execute(['org' => $organizacionId, 'email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function existeUsuarioEnOrg(int $organizacionId, string $usuario): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM usuarios WHERE organizacion_id = :org AND usuario = :usuario'
        );
        $stmt->execute(['org' => $organizacionId, 'usuario' => $usuario]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function generarUsuarioDesdeEmail(int $organizacionId, string $email): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9_.-]/i', '', strstr($email, '@', true) ?: $email) ?? '');
        $base = $base !== '' ? $base : 'usuario';
        $usuario = $base;
        $intento = 1;
        while (self::existeUsuarioEnOrg($organizacionId, $usuario)) {
            $intento++;
            $usuario = $base . $intento;
        }
        return $usuario;
    }
}
