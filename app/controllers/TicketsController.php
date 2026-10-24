<?php

declare(strict_types=1);

final class TicketsController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $estado = trim($_GET['estado'] ?? '');
        $resultado = TicketModel::paginate($org, $page, 20, $estado !== '' ? $estado : null);
        require dirname(__DIR__, 2) . '/views/modulos/tickets/index.php';
    }

    public function create(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare('SELECT id, nombre FROM activos WHERE organizacion_id = :org ORDER BY nombre');
        $stmt->execute(['org' => $org]);
        $activos = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/tickets/form.php';
    }

    public function store(): void
    {
        Auth::requireLogin();
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $descripcion = trim($_POST['descripcion'] ?? '');
        if ($descripcion === '') {
            $_SESSION['form_errors'] = ['descripcion' => 'Describe la falla reportada.'];
            header('Location: ' . base_url('/tickets/nuevo'));
            exit;
        }

        $usuario = Auth::user();
        $org = $usuario['organizacion_id'];

        $activoId = !empty($_POST['activo_id']) ? (int) $_POST['activo_id'] : null;
        if ($activoId !== null && ActivoModel::find($org, $activoId) === null) {
            $activoId = null;
        }

        $triage = (new AIService())->triageTicket($descripcion);

        $ticket = TicketModel::create($org, [
            'activo_id' => $activoId,
            'reportado_por_id' => $usuario['id'],
            'canal_origen' => 'web',
            'descripcion' => $descripcion,
            'categoria_ia' => $triage['categoria'] ?? null,
            'prioridad_ia' => $triage['prioridad'] ?? null,
        ]);

        header('Location: ' . base_url('/tickets/' . $ticket['id']));
        exit;
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $ticket = TicketModel::find($org, (int) $id);
        if (!$ticket) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            return;
        }
        require dirname(__DIR__, 2) . '/views/modulos/tickets/show.php';
    }

    public function updateStatus(string $id): void
    {
        Auth::requireRole('admin', 'supervisor', 'tecnico');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }
        $estadosValidos = ['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'];
        $estado = $_POST['estado'] ?? '';
        if (!in_array($estado, $estadosValidos, true)) {
            http_response_code(422);
            die('Estado inválido.');
        }
        $org = Auth::user()['organizacion_id'];
        TicketModel::updateStatus(
            $org,
            (int) $id,
            $estado,
            $_POST['prioridad_final'] ?? null,
            $_POST['categoria_final'] ?? null
        );
        header('Location: ' . base_url('/tickets/' . $id));
        exit;
    }
}
