<?php $pageTitle = $activo['nombre']; require __DIR__ . '/../../layouts/header.php'; $usuario = Auth::user(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= e($activo['nombre']) ?></h2>
    <div>
        <?php if (in_array($usuario['rol'], ['admin', 'supervisor'], true)): ?>
            <a href="<?= base_url('/activos/' . $activo['id'] . '/editar') ?>" class="btn btn-outline-secondary">Editar</a>
        <?php endif; ?>
        <?php if ($usuario['rol'] === 'admin'): ?>
            <form method="post" action="<?= base_url('/activos/' . $activo['id'] . '/eliminar') ?>" class="d-inline" data-confirm="¿Eliminar este activo? Esta acción no se puede deshacer.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger">Eliminar</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Datos del activo</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Tipo</dt><dd class="col-7"><?= e($activo['tipo'] ?? '—') ?></dd>
                    <dt class="col-5">Planta</dt><dd class="col-7"><?= e($activo['planta_nombre']) ?></dd>
                    <dt class="col-5">Ubicación</dt><dd class="col-7"><?= e($activo['ubicacion'] ?? '—') ?></dd>
                    <dt class="col-5">IP</dt><dd class="col-7"><?= e($activo['ip_address'] ?? '—') ?></dd>
                    <dt class="col-5">Nombre PROFINET</dt><dd class="col-7"><?= e($activo['nombre_profinet'] ?? '—') ?></dd>
                    <dt class="col-5">Firmware</dt><dd class="col-7"><?= e($activo['version_firmware'] ?? '—') ?></dd>
                    <dt class="col-5">Estado</dt><dd class="col-7"><span class="badge bg-light text-dark"><?= e($activo['estado']) ?></span></dd>
                    <dt class="col-5">Inactividad acumulada</dt><dd class="col-7"><?= number_format($downtimeHoras, 1) ?> hora(s)</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Historial de diagnóstico</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($diagnosticos as $d): ?>
                    <li class="list-group-item">
                        <span class="badge bg-secondary"><?= e($d['severidad']) ?></span>
                        <?= e($d['descripcion']) ?>
                        <div class="small text-secondary"><?= e($d['fecha_diagnostico']) ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$diagnosticos): ?>
                    <li class="list-group-item text-secondary">Sin diagnósticos registrados.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
