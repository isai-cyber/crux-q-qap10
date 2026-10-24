<?php

declare(strict_types=1);

// Servidor embebido de PHP (`php -S`, uso en desarrollo): sirve archivos estáticos
// directamente en vez de pasarlos por el router. En Apache esto ya lo resuelve el
// .htaccess (RewriteCond %{REQUEST_FILENAME} -f), por lo que aquí es un no-op.
if (PHP_SAPI === 'cli-server') {
    $staticFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($staticFile !== __DIR__ . '/' && is_file($staticFile)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

$routes = [
    'GET' => [
        '/' => ['AuthController', 'showLogin'],
        '/login' => ['AuthController', 'showLogin'],
        '/logout' => ['AuthController', 'logout'],
        '/registro' => ['RegistroController', 'showForm'],
        '/dashboard' => ['DashboardController', 'index'],

        '/catalogo' => ['CatalogoController', 'index'],
        '/catalogo/{id}' => ['CatalogoController', 'show'],

        '/ordenes-compra' => ['OrdenesCompraController', 'index'],
        '/ordenes-compra/nueva' => ['OrdenesCompraController', 'create'],

        '/activos' => ['ActivosController', 'index'],
        '/activos/nuevo' => ['ActivosController', 'create'],
        '/activos/{id}' => ['ActivosController', 'show'],
        '/activos/{id}/editar' => ['ActivosController', 'edit'],

        '/tickets' => ['TicketsController', 'index'],
        '/tickets/nuevo' => ['TicketsController', 'create'],
        '/tickets/{id}' => ['TicketsController', 'show'],

        '/chat' => ['ChatController', 'index'],

        '/ordenes' => ['OrdenesController', 'index'],
        '/ordenes/{id}' => ['OrdenesController', 'show'],

        '/runbooks' => ['RunbooksController', 'index'],

        '/mantenimiento-preventivo' => ['PlanMantenimientoController', 'index'],
        '/mantenimiento-preventivo/nuevo' => ['PlanMantenimientoController', 'create'],

        '/disenos' => ['DisenosController', 'index'],
        '/disenos/nuevo' => ['DisenosController', 'create'],
        '/disenos/{id}' => ['DisenosController', 'show'],

        '/conocimiento' => ['GrafoController', 'index'],

        '/suscripcion' => ['SuscripcionController', 'index'],
        '/suscripcion/checkout' => ['SuscripcionController', 'checkout'],
        '/suscripcion/portal' => ['SuscripcionController', 'portal'],
        '/suscripcion/exito' => ['SuscripcionController', 'exito'],
        '/reportes' => ['ReportesController', 'index'],
        '/optimizacion-cuantica' => ['OptimizacionController', 'index'],
        '/reportes/tickets/exportar' => ['ReportesController', 'exportTickets'],

        '/admin/usuarios' => ['UsuariosController', 'index'],
        '/admin/organizacion' => ['OrganizacionController', 'editar'],
        '/tokens' => ['ApiTokensController', 'index'],
        '/admin/tokens' => ['ApiTokensController', 'index'],

        '/api/v1/activos/{id}' => ['ApiController', 'consultarActivo'],
        '/api/v1/catalogo/buscar' => ['ApiController', 'buscarCatalogo'],
        '/api/v1/alertas/pendientes' => ['ApiController', 'alertasPendientes'],

        '/api/v1/me' => ['AuthApiController', 'me'],
        '/api/v1/tickets' => ['AuthApiController', 'tickets'],
        '/api/v1/tickets/{id}' => ['AuthApiController', 'ticket'],
        '/api/v1/activos' => ['AuthApiController', 'activos'],
        '/api/v1/dashboard' => ['AuthApiController', 'dashboard'],
        '/api/v1/uso-ia' => ['AuthApiController', 'usoIA'],
    ],
    'POST' => [
        '/login' => ['AuthController', 'login'],
        '/logout' => ['AuthController', 'logout'],
        '/registro' => ['RegistroController', 'store'],

        '/activos' => ['ActivosController', 'store'],
        '/activos/{id}' => ['ActivosController', 'update'],
        '/activos/{id}/eliminar' => ['ActivosController', 'destroy'],

        '/tickets' => ['TicketsController', 'store'],
        '/tickets/{id}/estado' => ['TicketsController', 'updateStatus'],

        '/chat/enviar' => ['ChatController', 'enviar'],
        '/optimizacion-cuantica/ejecutar' => ['OptimizacionController', 'ejecutar'],

        '/ordenes/{id}' => ['OrdenesController', 'update'],

        '/disenos' => ['DisenosController', 'store'],
        '/mantenimiento-preventivo' => ['PlanMantenimientoController', 'store'],
        '/mantenimiento-preventivo/{id}/eliminar' => ['PlanMantenimientoController', 'destroy'],

        '/ordenes-compra' => ['OrdenesCompraController', 'store'],
        '/ordenes-compra/{id}/aprobar' => ['OrdenesCompraController', 'aprobar'],
        '/ordenes-compra/{id}/rechazar' => ['OrdenesCompraController', 'rechazar'],
        '/ordenes-compra/{id}/recibir' => ['OrdenesCompraController', 'recibir'],
        '/suscripcion/cancelar' => ['SuscripcionController', 'cancelar'],
        '/suscripcion/reembolso' => ['SuscripcionController', 'reembolso'],
        '/suscripcion/activar-enterprise' => ['SuscripcionController', 'activarEnterprise'],

        '/api/v1/tickets' => ['ApiController', 'crearTicket'],
        '/api/v1/activos' => ['ApiController', 'crearActivo'],
        '/api/v1/usuarios' => ['ApiController', 'crearUsuario'],
        '/mcp' => ['McpController', 'handle'],

        '/api/v1/auth/login' => ['AuthApiController', 'login'],
        '/api/v1/chat' => ['AuthApiController', 'chat'],
        '/api/v1/tickets/{id}/estado' => ['AuthApiController', 'cambiarEstado'],

        '/admin/usuarios' => ['UsuariosController', 'store'],
        '/admin/usuarios/{id}' => ['UsuariosController', 'update'],
        '/admin/organizacion/logo' => ['OrganizacionController', 'actualizarLogo'],
        '/admin/organizacion/logo/eliminar' => ['OrganizacionController', 'eliminarLogo'],
        '/admin/organizacion/ia' => ['OrganizacionController', 'actualizarIA'],
        '/admin/organizacion/mcp/{nombre}/eliminar' => ['OrganizacionController', 'eliminarConexionMcp'],
        '/tokens' => ['ApiTokensController', 'store'],
        '/tokens/{id}/revocar' => ['ApiTokensController', 'revoke'],
        '/admin/tokens' => ['ApiTokensController', 'store'],
        '/admin/tokens/{id}/revocar' => ['ApiTokensController', 'revoke'],
    ],
];

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$prefix = base_path();
if ($prefix !== '' && str_starts_with($uri, $prefix)) {
    $uri = substr($uri, strlen($prefix));
}
$uri = '/' . trim($uri, '/');
if ($uri !== '/' && $uri === '') {
    $uri = '/';
}

$handler = null;
$params = [];

foreach ($routes[$method] ?? [] as $pattern => $target) {
    $regex = '#^' . preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $pattern) . '$#';
    if (preg_match($regex, $uri, $matches)) {
        $handler = $target;
        $params = array_slice($matches, 1);
        break;
    }
}

if ($handler === null) {
    http_response_code(404);
    require dirname(__DIR__) . '/views/errors/404.php';
    exit;
}

[$controllerName, $action] = $handler;

if (!class_exists($controllerName)) {
    http_response_code(500);
    log_error("Controlador no encontrado: {$controllerName}");
    echo 'Error interno del servidor.';
    exit;
}

$controller = new $controllerName();
$controller->$action(...$params);
