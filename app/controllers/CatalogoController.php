<?php

declare(strict_types=1);

final class CatalogoController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim($_GET['q'] ?? '');
        $categoriaId = !empty($_GET['categoria']) ? (int) $_GET['categoria'] : null;
        $resultado = ProductoModel::paginate($org, $page, 20, $search !== '' ? $search : null, $categoriaId);
        $categorias = ProductoModel::categorias();
        require dirname(__DIR__, 2) . '/views/modulos/catalogo/index.php';
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $producto = ProductoModel::find($org, (int) $id);
        if (!$producto) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            return;
        }
        require dirname(__DIR__, 2) . '/views/modulos/catalogo/show.php';
    }
}
