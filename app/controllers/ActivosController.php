<?php

declare(strict_types=1);

final class ActivosController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim($_GET['q'] ?? '');
        $resultado = ActivoModel::paginate($org, $page, 20, $search !== '' ? $search : null);
        require dirname(__DIR__, 2) . '/views/modulos/activos/index.php';
    }

    public function create(): void
    {
        Auth::requireRole('admin', 'supervisor');
        $org = Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare('SELECT * FROM plantas WHERE organizacion_id = :org ORDER BY nombre');
        $stmt->execute(['org' => $org]);
        $plantas = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/activos/form.php';
    }

    public function store(): void
    {
        Auth::requireRole('admin', 'supervisor');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = $_POST;
            header('Location: ' . base_url('/activos/nuevo'));
            exit;
        }

        $org = Auth::user()['organizacion_id'];
        $id = ActivoModel::create($org, $_POST);
        header('Location: ' . base_url('/activos/' . $id));
        exit;
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $activo = ActivoModel::find($org, (int) $id);
        if (!$activo) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            return;
        }
        $diagnosticos = ActivoModel::diagnosticos((int) $id);
        $downtimeHoras = ActivoModel::downtimeHoras($org, (int) $id);
        require dirname(__DIR__, 2) . '/views/modulos/activos/show.php';
    }

    public function edit(string $id): void
    {
        Auth::requireRole('admin', 'supervisor');
        $org = Auth::user()['organizacion_id'];
        $activo = ActivoModel::find($org, (int) $id);
        if (!$activo) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            return;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM plantas WHERE organizacion_id = :org ORDER BY nombre');
        $stmt->execute(['org' => $org]);
        $plantas = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/activos/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireRole('admin', 'supervisor');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }
        $errors = $this->validate($_POST);
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = $_POST;
            header('Location: ' . base_url('/activos/' . $id . '/editar'));
            exit;
        }
        $org = Auth::user()['organizacion_id'];
        ActivoModel::update($org, (int) $id, $_POST);
        header('Location: ' . base_url('/activos/' . $id));
        exit;
    }

    public function destroy(string $id): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }
        $org = Auth::user()['organizacion_id'];
        ActivoModel::delete($org, (int) $id);
        header('Location: ' . base_url('/activos'));
        exit;
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (trim($data['nombre'] ?? '') === '') {
            $errors['nombre'] = 'El nombre es obligatorio.';
        }
        if (empty($data['planta_id'])) {
            $errors['planta_id'] = 'Debe seleccionar una planta.';
        } else {
            $org = Auth::user()['organizacion_id'];
            $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM plantas WHERE id = :id AND organizacion_id = :org');
            $stmt->execute(['id' => $data['planta_id'], 'org' => $org]);
            if ((int) $stmt->fetchColumn() === 0) {
                $errors['planta_id'] = 'Planta no válida.';
            }
        }
        if (!empty($data['ip_address']) && !filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
            $errors['ip_address'] = 'La dirección IP no es válida.';
        }
        return $errors;
    }
}
