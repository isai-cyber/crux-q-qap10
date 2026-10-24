<?php

declare(strict_types=1);

/**
 * Alta de una nueva organización (tenant) con su primer usuario admin.
 * Punto de entrada público del micro SaaS: sin esto, solo la organización
 * sembrada por seed.sql podría usar el sistema.
 */
final class RegistroController
{
    public function showForm(): void
    {
        if (Auth::check()) {
            header('Location: ' . base_url('/dashboard'));
            exit;
        }
        $errors = $_SESSION['form_errors'] ?? [];
        $old = $_SESSION['form_old'] ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_old']);
        require dirname(__DIR__, 2) . '/views/auth/registro.php';
    }

    public function store(): void
    {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $nombreOrg = trim($_POST['organizacion'] ?? '');
        $nombreAdmin = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];
        if ($nombreOrg === '') {
            $errors[] = 'El nombre de la organización es obligatorio.';
        }
        if ($nombreAdmin === '') {
            $errors[] = 'Tu nombre es obligatorio.';
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $usuario)) {
            $errors[] = 'El usuario debe tener entre 3 y 60 caracteres (letras, números, puntos, guiones o guiones bajos).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = ['organizacion' => $nombreOrg, 'nombre' => $nombreAdmin, 'usuario' => $usuario, 'email' => $email];
            header('Location: ' . base_url('/registro'));
            exit;
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $slug = $this->generarSlugUnico($nombreOrg);

            $db->prepare('INSERT INTO organizaciones (nombre, slug, plan) VALUES (:nombre, :slug, :plan)')
                ->execute(['nombre' => $nombreOrg, 'slug' => $slug, 'plan' => 'trial']);
            $organizacionId = (int) $db->lastInsertId();

            $db->prepare('INSERT INTO plantas (organizacion_id, nombre) VALUES (:org, :nombre)')
                ->execute(['org' => $organizacionId, 'nombre' => 'Planta principal']);
            $plantaId = (int) $db->lastInsertId();

            $usuarioId = UsuarioModel::create($organizacionId, [
                'nombre' => $nombreAdmin,
                'usuario' => $usuario,
                'email' => $email,
                'password' => $password,
                'rol' => 'admin',
                'planta_id' => $plantaId,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            log_error('Error en registro de organización: ' . $e->getMessage());
            $_SESSION['form_errors'] = ['No se pudo completar el registro. Intenta de nuevo.'];
            header('Location: ' . base_url('/registro'));
            exit;
        }

        $usuario = UsuarioModel::find($organizacionId, $usuarioId);
        Auth::login($usuario);
        header('Location: ' . base_url('/admin/organizacion?onboarding=1'));
        exit;
    }

    private function generarSlugUnico(string $nombre): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $nombre), '-'));
        $base = $base !== '' ? $base : 'empresa';
        $slug = $base;
        $intento = 1;

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM organizaciones WHERE slug = :slug');
        while (true) {
            $stmt->execute(['slug' => $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $intento++;
            $slug = $base . '-' . $intento;
        }
    }
}
