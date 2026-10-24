<?php
/** @var string|null $pageTitle */
$usuario = Auth::user();
$appName = (require dirname(__DIR__, 2) . '/app/config/config.php')['app']['name'];
$rutaActual = '/' . trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Whitelabel: si la organización subió su propio logo, reemplaza la marca
// de Crux A.I en el sidebar/header.
$marcaNombre = $appName;
$marcaLogoUrl = base_url('/img/logo.png');
if ($usuario) {
    $stmtOrgMarca = Database::connection()->prepare('SELECT nombre, logo_path FROM organizaciones WHERE id = :id LIMIT 1');
    $stmtOrgMarca->execute(['id' => $usuario['organizacion_id']]);
    $orgMarca = $stmtOrgMarca->fetch();
    if ($orgMarca) {
        $marcaNombre = $orgMarca['nombre'];
        if (!empty($orgMarca['logo_path'])) {
            $marcaLogoUrl = base_url('/uploads/logos/' . $orgMarca['logo_path']);
        }
    }
}

$navItems = [
    ['href' => '/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    ['href' => '/chat', 'label' => 'ORION', 'icon' => 'bi-chat-dots'],
    ['href' => '/tickets', 'label' => 'Tickets', 'icon' => 'bi-ticket-detailed'],
    ['href' => '/ordenes', 'label' => 'Órdenes de trabajo', 'icon' => 'bi-clipboard-check'],
    ['href' => '/activos', 'label' => 'Activos (CMDB)', 'icon' => 'bi-hdd-network'],
    ['href' => '/mantenimiento-preventivo', 'label' => 'Mant. Preventivo', 'icon' => 'bi-calendar-check'],
    ['href' => '/catalogo', 'label' => 'Catálogo', 'icon' => 'bi-boxes'],
    ['href' => '/ordenes-compra', 'label' => 'Órdenes de Compra', 'icon' => 'bi-cart-check'],
    ['href' => '/runbooks', 'label' => 'Runbooks', 'icon' => 'bi-arrow-repeat'],
    ['href' => '/disenos', 'label' => 'Diseños técnicos IA', 'icon' => 'bi-magic'],
    ['href' => '/conocimiento', 'label' => 'Mapa de conocimiento 3D', 'icon' => 'bi-diagram-3'],
    ['href' => '/reportes', 'label' => 'Reportes', 'icon' => 'bi-bar-chart-line'],
    ['href' => '/optimizacion-cuantica', 'label' => 'Optimización cuántica', 'icon' => 'bi-cpu'],
    ['href' => '/tokens', 'label' => 'Mis API Keys', 'icon' => 'bi-key'],
];
$navAdmin = [
    ['href' => '/admin/usuarios', 'label' => 'Usuarios', 'icon' => 'bi-people'],
    ['href' => '/admin/organizacion', 'label' => 'Mi Organización', 'icon' => 'bi-building'],
];

// Iniciales para el avatar (ej. "Armando Mercado" -> "AM")
$iniciales = implode('', array_map(
    fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)),
    array_slice(preg_split('/\s+/', trim($usuario['nombre'] ?? '?')) ?: [], 0, 2)
));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= base_url('/img/logo.png') ?>">
    <title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e($marcaNombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= base_url('/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<?php if ($usuario): ?>
<!-- Barra superior móvil -->
<div class="d-md-none topbar-movil border-bottom p-3 d-flex align-items-center justify-content-between sticky-top shadow-sm">
    <a href="<?= base_url('/dashboard') ?>" class="d-flex align-items-center gap-2 text-decoration-none">
        <span class="brand-tile" style="width: 32px; height: 32px;"><img src="<?= e($marcaLogoUrl) ?>" alt="Logo"></span>
        <span class="brand-wordmark text-dark" style="font-size: 1.05rem;"><?= e($marcaNombre) ?></span>
    </a>
    <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
        <i class="bi bi-list fs-5"></i> Menú
    </button>
</div>

<!-- Offcanvas Sidebar para móviles -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom" style="border-color: rgba(255,255,255,0.1) !important;">
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="mobileSidebarLabel">
            <span class="brand-tile" style="width: 32px; height: 32px;"><img src="<?= e($marcaLogoUrl) ?>" alt="Logo"></span>
            <?= e($marcaNombre) ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3 d-flex flex-column justify-content-between">
        <ul class="nav nav-pills flex-column gap-1">
            <?php foreach ($navItems as $item): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2 <?= $rutaActual === $item['href'] ? 'active' : '' ?>" href="<?= base_url($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if ($usuario['rol'] === 'admin'): ?>
            <li class="nav-item mt-3"><small class="text-uppercase sidebar-section px-3">Administración</small></li>
            <?php foreach ($navAdmin as $item): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2 <?= $rutaActual === $item['href'] ? 'active' : '' ?>" href="<?= base_url($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <div class="sidebar-footer mt-3">
            <div class="small d-flex align-items-center gap-2 mb-3 text-white">
                <span class="avatar-iniciales"><?= e($iniciales ?: '?') ?></span>
                <span class="text-truncate"><?= e($usuario['nombre']) ?><br><span class="rol-badge"><?= e($usuario['rol']) ?></span></span>
            </div>
            <form method="post" action="<?= base_url('/logout') ?>">
                <button class="btn btn-outline-light btn-sm w-100" type="submit"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>

<div class="d-flex">
    <!-- Sidebar de escritorio -->
    <nav class="sidebar p-3 flex-shrink-0 d-none d-md-flex flex-column">
        <h5 class="mb-4 mt-2 d-flex align-items-center gap-2 px-1">
            <span class="brand-tile"><img src="<?= e($marcaLogoUrl) ?>" alt="Logo"></span>
            <?= e($marcaNombre) ?>
        </h5>
        <ul class="nav nav-pills flex-column gap-1">
            <?php foreach ($navItems as $item): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2 <?= $rutaActual === $item['href'] ? 'active' : '' ?>" href="<?= base_url($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if ($usuario['rol'] === 'admin'): ?>
            <li class="nav-item mt-3 mb-1"><small class="text-uppercase sidebar-section px-3">Administración</small></li>
            <?php foreach ($navAdmin as $item): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2 <?= $rutaActual === $item['href'] ? 'active' : '' ?>" href="<?= base_url($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <div class="sidebar-footer mt-auto pt-3">
            <div class="dropdown">
                <button class="btn btn-link text-decoration-none p-0 w-100 d-flex align-items-center gap-2 text-start" data-bs-toggle="dropdown" aria-expanded="false" style="color: #fff;">
                    <span class="avatar-iniciales"><?= e($iniciales ?: '?') ?></span>
                    <span class="text-truncate small lh-sm">
                        <?= e($usuario['nombre']) ?><br>
                        <span class="rol-badge"><?= e($usuario['rol']) ?></span>
                    </span>
                    <i class="bi bi-chevron-up ms-auto small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark shadow p-2" style="min-width: 200px;">
                    <li>
                        <form method="post" action="<?= base_url('/logout') ?>">
                            <button class="dropdown-item text-danger d-flex align-items-center gap-2" type="submit">
                                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="flex-fill p-3 p-md-4" style="min-width: 0;">
<?php else: ?>
    <main class="p-3 p-md-4">
<?php endif; ?>
