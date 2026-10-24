<?php

declare(strict_types=1);

final class OrdenesCompraController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = (int) Auth::user()['organizacion_id'];
        $estado = trim($_GET['estado'] ?? '');
        $ordenes = OrdenCompraModel::all($org, $estado !== '' ? $estado : null);
        require dirname(__DIR__, 2) . '/views/modulos/ordenes_compra/index.php';
    }

    public function create(): void
    {
        Auth::requireLogin();
        $org = (int) Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare('SELECT id, numero_parte, descripcion FROM productos_catalogo ORDER BY numero_parte');
        $stmt->execute();
        $productos = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/ordenes_compra/form.php';
    }

    public function store(): void
    {
        Auth::requireLogin();
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $org = (int) Auth::user()['organizacion_id'];
        $errors = $this->validar($_POST);
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = $_POST;
            header('Location: ' . base_url('/ordenes-compra/nueva'));
            exit;
        }

        OrdenCompraModel::crear($org, $_POST, (int) Auth::user()['id']);
        header('Location: ' . base_url('/ordenes-compra'));
        exit;
    }

    public function aprobar(string $id): void
    {
        Auth::requireRole('admin', 'supervisor');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $org = (int) Auth::user()['organizacion_id'];
        OrdenCompraModel::aprobar($org, (int) $id, (int) Auth::user()['id']);
        header('Location: ' . base_url('/ordenes-compra'));
        exit;
    }

    public function rechazar(string $id): void
    {
        Auth::requireRole('admin', 'supervisor');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $org = (int) Auth::user()['organizacion_id'];
        OrdenCompraModel::rechazar($org, (int) $id, (int) Auth::user()['id']);
        header('Location: ' . base_url('/ordenes-compra'));
        exit;
    }

    public function recibir(string $id): void
    {
        Auth::requireRole('admin', 'supervisor');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $org = (int) Auth::user()['organizacion_id'];
        OrdenCompraModel::recibir($org, (int) $id, (int) Auth::user()['id']);
        header('Location: ' . base_url('/ordenes-compra'));
        exit;
    }

    private function validar(array $data): array
    {
        $errors = [];
        if (empty($data['producto_id'])) {
            $errors[] = 'Selecciona un producto.';
        }
        if ((int) ($data['cantidad'] ?? 0) < 1) {
            $errors[] = 'La cantidad debe ser al menos 1.';
        }
        return $errors;
    }
}
