<?php
$pageTitle = 'Mi Organización';
require __DIR__ . '/../../layouts/header.php';

$logoActualUrl = !empty($org['logo_path'])
    ? base_url('/uploads/logos/' . $org['logo_path'])
    : base_url('/img/logo.png');

$aiConfigurada = !empty($org['ai_api_key']);
$aiKeyMascara = $aiConfigurada
    ? str_repeat('•', 8) . mb_substr((string) $org['ai_api_key'], -4)
    : '';
?>

<div class="container-fluid py-2">
    <h3 class="mb-1 text-dark fw-bold d-flex align-items-center gap-2">
        <i class="bi bi-building text-primary"></i> Mi Organización
    </h3>
    <p class="text-secondary mb-4 small">
        Configuración de instalación de tu organización: marca, clave de IA, tu equipo y
        conexiones a otras herramientas MCP.
    </p>

    <?php if (!empty($onboarding)): ?>
        <div class="alert alert-primary d-flex align-items-start gap-2 py-3 mb-4" role="alert">
            <i class="bi bi-rocket-takeoff-fill fs-4"></i>
            <div>
                <strong>¡Organización creada! Antes de empezar:</strong>
                <ol class="mb-0 mt-1 small">
                    <li>Sube tu logo (opcional).</li>
                    <li>Configura tu clave de IA si quieres clasificación real de tickets (opcional — sin ella, ORION usa un heurístico de respaldo, sin costo).</li>
                    <li>Da de alta a tu equipo desde <a href="<?= base_url('/admin/usuarios') ?>">Administración → Usuarios</a>.</li>
                </ol>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Logo -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-image text-primary"></i> Logo</h6>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 border rounded-3 bg-light">
                            <img src="<?= e($logoActualUrl) ?>" alt="Logo actual" style="height: 56px; max-width: 160px; object-fit: contain;">
                        </div>
                        <div class="small text-secondary">
                            <?= !empty($org['logo_path']) ? 'Logo personalizado de ' . e($org['nombre']) : 'Usando el logo predeterminado de Crux A.I' ?>
                        </div>
                    </div>

                    <form method="post" action="<?= base_url('/admin/organizacion/logo') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
                            <small class="text-muted">PNG, JPG, WEBP o SVG · máximo 2 MB.</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-upload"></i> Guardar logo
                        </button>
                    </form>

                    <?php if (!empty($org['logo_path'])): ?>
                        <form method="post" action="<?= base_url('/admin/organizacion/logo/eliminar') ?>" class="mt-2 d-inline" onsubmit="return confirm('¿Quitar el logo personalizado y volver al predeterminado?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash"></i> Quitar logo
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Clave de IA -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-cpu text-primary"></i> Clave de IA</h6>
                    <p class="small text-secondary mb-3">
                        <?php if ($aiConfigurada): ?>
                            Configurada: <code><?= e($aiKeyMascara) ?></code> (<?= e($org['ai_provider']) ?>). ORION y el
                            triage de tickets usan esta clave en vez de la del sistema.
                        <?php else: ?>
                            Sin configurar todavía — ORION y el triage usan el respaldo del sistema (o un
                            heurístico sin IA si tampoco hay respaldo).
                        <?php endif; ?>
                    </p>

                    <form method="post" action="<?= base_url('/admin/organizacion/ia') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small">Proveedor</label>
                            <select name="ai_provider" class="form-select form-select-sm">
                                <?php foreach (['deepseek' => 'DeepSeek', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic'] as $valor => $etiqueta): ?>
                                    <option value="<?= e($valor) ?>" <?= ($org['ai_provider'] ?? 'deepseek') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Clave de API</label>
                            <input type="password" name="ai_api_key" class="form-control form-control-sm" placeholder="<?= $aiConfigurada ? 'Dejar en blanco para no cambiarla' : 'sk-...' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Modelo (opcional)</label>
                            <input type="text" name="ai_model" class="form-control form-control-sm" value="<?= e($org['ai_model'] ?? '') ?>" placeholder="ej. deepseek-chat">
                        </div>
                        <?php if ($aiConfigurada): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="quitar_clave" value="1" id="quitarClave">
                                <label class="form-check-label small" for="quitarClave">Quitar la clave configurada</label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Equipo -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-2"><i class="bi bi-people text-primary"></i> Tu equipo</h6>
                        <p class="small text-secondary mb-3">
                            <?= (int) $totalUsuarios ?> usuario<?= $totalUsuarios === 1 ? '' : 's' ?> registrado<?= $totalUsuarios === 1 ? '' : 's' ?> en tu organización.
                        </p>
                    </div>
                    <a href="<?= base_url('/admin/usuarios') ?>" class="btn btn-outline-primary btn-sm align-self-start">
                        <i class="bi bi-person-plus"></i> Crear / administrar usuarios
                    </a>
                </div>
            </div>
        </div>

        <!-- Conexiones MCP -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-plug text-primary"></i> Conexiones MCP</h6>
                    <p class="small text-secondary mb-3">
                        ORION puede usar herramientas de otros servidores MCP del mismo rubro (otro CMMS,
                        un scanner de red, un ERP de repuestos, etc.). Se agregan por chat, no aquí:
                        <br><code>conectar mcp: nombre, https://servidor.com/mcp, token</code>
                    </p>

                    <?php if (empty($conexionesMcp)): ?>
                        <p class="small text-muted mb-0">Aún no hay conexiones MCP configuradas.</p>
                    <?php else: ?>
                        <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                            <?php foreach ($conexionesMcp as $c): ?>
                                <?php $herramientas = json_decode($c['herramientas_cache'] ?? '[]', true) ?: []; ?>
                                <li class="d-flex align-items-center justify-content-between border rounded-3 p-2">
                                    <div class="small">
                                        <strong><?= e($c['nombre']) ?></strong>
                                        <span class="text-muted d-block text-truncate" style="max-width: 260px;"><?= e($c['url']) ?></span>
                                        <span class="badge bg-light text-dark"><?= count($herramientas) ?> herramienta(s)</span>
                                    </div>
                                    <form method="post" action="<?= base_url('/admin/organizacion/mcp/' . urlencode($c['nombre']) . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar la conexión MCP \'<?= e($c['nombre']) ?>\'?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
