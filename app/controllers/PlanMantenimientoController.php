<?php

declare(strict_types=1);

final class PlanMantenimientoController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = (int) Auth::user()['organizacion_id'];

        // Generación perezosa: revisa vencidos cada vez que se visita el
        // módulo, así funciona sin depender de que haya un cron configurado.
        $generados = PlanMantenimientoModel::generarPendientes($org);

        $planes = PlanMantenimientoModel::all($org);
        require dirname(__DIR__, 2) . '/views/modulos/preventivo/index.php';
    }

    public function create(): void
    {
        Auth::requireRole('admin', 'supervisor');
        $org = (int) Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare('SELECT id, nombre FROM activos WHERE organizacion_id = :org ORDER BY nombre');
        $stmt->execute(['org' => $org]);
        $activos = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/preventivo/form.php';
    }

    public function store(): void
    {
        Auth::requireRole('admin', 'supervisor');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $org = (int) Auth::user()['organizacion_id'];
        $errors = $this->validar($org, $_POST);
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = $_POST;
            header('Location: ' . base_url('/mantenimiento-preventivo/nuevo'));
            exit;
        }

        PlanMantenimientoModel::create($org, $_POST, (int) Auth::user()['id']);
        header('Location: ' . base_url('/mantenimiento-preventivo'));
        exit;
    }

    public function destroy(string $id): void
    {
        Auth::requireRole('admin', 'supervisor');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $org = (int) Auth::user()['organizacion_id'];
        PlanMantenimientoModel::eliminar($org, (int) $id);
        header('Location: ' . base_url('/mantenimiento-preventivo'));
        exit;
    }

    private function validar(int $org, array $data): array
    {
        $errors = [];
        if (trim($data['titulo'] ?? '') === '') {
            $errors[] = 'El título es obligatorio.';
        }
        if (empty($data['activo_id']) || !ActivoModel::find($org, (int) $data['activo_id'])) {
            $errors[] = 'Selecciona un activo válido.';
        }
        $frecuencia = (int) ($data['frecuencia_dias'] ?? 0);
        if ($frecuencia < 1) {
            $errors[] = 'La frecuencia debe ser de al menos 1 día.';
        }
        if (empty($data['proxima_fecha']) || !strtotime($data['proxima_fecha'])) {
            $errors[] = 'La fecha de inicio no es válida.';
        }
        return $errors;
    }
}
