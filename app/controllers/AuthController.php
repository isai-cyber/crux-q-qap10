<?php

declare(strict_types=1);

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . base_url('/dashboard'));
            exit;
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        // Whitelabel: /login?org=slug muestra el logo/nombre de esa
        // organización en vez de la marca genérica (deep link por cliente).
        $orgBranding = null;
        $slug = trim($_GET['org'] ?? '');
        if ($slug !== '') {
            $stmt = Database::connection()->prepare('SELECT nombre, logo_path FROM organizaciones WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            $orgBranding = $stmt->fetch() ?: null;
        }

        require dirname(__DIR__, 2) . '/views/auth/login.php';
    }

    public function login(): void
    {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['login_error'] = 'Sesión inválida, intente de nuevo.';
            header('Location: ' . base_url('/login'));
            exit;
        }

        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = null;
        foreach (UsuarioModel::findActiveByUsername($usuario) as $candidato) {
            if (password_verify($password, $candidato['password_hash'])) {
                $user = $candidato;
                break;
            }
        }

        if (!$user) {
            $_SESSION['login_error'] = 'Credenciales incorrectas.';
            header('Location: ' . base_url('/login'));
            exit;
        }

        Auth::login($user);
        header('Location: ' . base_url('/dashboard'));
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: ' . base_url('/login'));
        exit;
    }
}
