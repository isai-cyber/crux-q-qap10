<?php

declare(strict_types=1);

final class UsuariosController
{
    public function index(): void
    {
        Auth::requireRole('admin');
        $org = Auth::user()['organizacion_id'];
        $usuarios = UsuarioModel::all($org);
        $stmt = Database::connection()->prepare('SELECT * FROM plantas WHERE organizacion_id = :org ORDER BY nombre');
        $stmt->execute(['org' => $org]);
        $plantas = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/usuarios/index.php';
    }

    public function store(): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $org = Auth::user()['organizacion_id'];
        $errors = $this->validar($org, $_POST);

        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            header('Location: ' . base_url('/admin/usuarios'));
            exit;
        }

        UsuarioModel::create($org, $_POST);
        header('Location: ' . base_url('/admin/usuarios'));
        exit;
    }

    public function update(string $id): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }
        $org = Auth::user()['organizacion_id'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        $stmt = Database::connection()->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id AND organizacion_id = :org');
        $stmt->execute(['activo' => $activo, 'id' => $id, 'org' => $org]);
        header('Location: ' . base_url('/admin/usuarios'));
        exit;
    }

    public function validar(int $organizacionId, array $data): array
    {
        $errors = [];
        if (trim($data['nombre'] ?? '') === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        $usuario = trim($data['usuario'] ?? '');
        if ($usuario !== '') {
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $usuario)) {
                $errors[] = 'El usuario debe tener entre 3 y 60 caracteres (letras, números, puntos, guiones o guiones bajos).';
            } elseif (UsuarioModel::existeUsuarioEnOrg($organizacionId, $usuario)) {
                $errors[] = 'Ya existe un usuario con ese nombre de usuario en tu organización.';
            }
        }
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido.';
        } elseif (UsuarioModel::existeEmailEnOrg($organizacionId, $data['email'])) {
            $errors[] = 'Ya existe un usuario con ese email en tu organización.';
        }
        if (strlen($data['password'] ?? '') < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!in_array($data['rol'] ?? '', ['admin', 'supervisor', 'tecnico'], true)) {
            $errors[] = 'Rol inválido.';
        }
        return $errors;
    }
}
