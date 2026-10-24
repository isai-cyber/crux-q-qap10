<?php

declare(strict_types=1);

final class ApiTokensController
{
    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $userId = (int) $user["id"];
        $orgId = (int) $user["organizacion_id"];

        // Cada usuario solo ve y gestiona sus propias API Keys personales
        $stmt = Database::connection()->prepare(
            "SELECT id, nombre, permisos, activo, ultimo_uso, created_at 
             FROM api_tokens 
             WHERE organizacion_id = :org AND usuario_creador_id = :uid 
             ORDER BY created_at DESC"
        );
        $stmt->execute([
            "org" => $orgId,
            "uid" => $userId,
        ]);
        $tokens = $stmt->fetchAll();
        
        $tokenPlano = $_SESSION["nuevo_token_plano"] ?? null;
        $tokenNombre = $_SESSION["nuevo_token_nombre"] ?? null;
        unset($_SESSION["nuevo_token_plano"], $_SESSION["nuevo_token_nombre"]);
        
        require dirname(__DIR__, 2) . "/views/modulos/tokens/index.php";
    }

    public function store(): void
    {
        Auth::requireLogin();
        if (!csrf_verify($_POST["csrf_token"] ?? null)) {
            http_response_code(419);
            die("Token de seguridad inválido.");
        }

        $usuario = Auth::user();
        $nombre = trim($_POST["nombre"] ?? "");
        if ($nombre === "") {
            $nombre = "API Key - " . ($usuario["nombre"] ?? "Usuario");
        }

        $permisosPost = (array) ($_POST["permisos"] ?? []);
        $todosPermisos = [
            "crear_ticket",
            "consultar_activos",
            "crear_activo",
            "consultar_catalogo",
            "consultar_alertas",
            "crear_usuario",
            "consultar_tickets",
            "actualizar_ticket",
            "usar_chat"
        ];

        if (empty($permisosPost) || in_array("all", $permisosPost, true)) {
            $permisos = $todosPermisos;
        } else {
            $permisos = array_values(array_intersect($permisosPost, $todosPermisos));
            if (empty($permisos)) {
                $permisos = $todosPermisos;
            }
        }

        $tokenPlano = "crux_" . bin2hex(random_bytes(24));
        $stmt = Database::connection()->prepare(
            "INSERT INTO api_tokens (organizacion_id, nombre, token_hash, permisos, usuario_creador_id, activo)
             VALUES (:org, :nombre, :token_hash, :permisos, :usuario_id, 1)"
        );
        $stmt->execute([
            "org" => (int) $usuario["organizacion_id"],
            "nombre" => $nombre,
            "token_hash" => hash("sha256", $tokenPlano),
            "permisos" => json_encode($permisos),
            "usuario_id" => (int) $usuario["id"],
        ]);

        $_SESSION["nuevo_token_plano"] = $tokenPlano;
        $_SESSION["nuevo_token_nombre"] = $nombre;
        
        $redirect = str_contains($_SERVER["REQUEST_URI"], "admin") ? "/admin/tokens" : "/tokens";
        header("Location: " . base_url($redirect));
        exit;
    }

    public function revoke(string $id): void
    {
        Auth::requireLogin();
        if (!csrf_verify($_POST["csrf_token"] ?? null)) {
            http_response_code(419);
            die("Token de seguridad inválido.");
        }

        $usuario = Auth::user();
        $stmt = Database::connection()->prepare(
            "UPDATE api_tokens SET activo = 0 WHERE id = :id AND usuario_creador_id = :uid"
        );
        $stmt->execute([
            "id" => (int) $id, 
            "uid" => (int) $usuario["id"]
        ]);
        
        $redirect = str_contains($_SERVER["REQUEST_URI"], "admin") ? "/admin/tokens" : "/tokens";
        header("Location: " . base_url($redirect));
        exit;
    }
}
