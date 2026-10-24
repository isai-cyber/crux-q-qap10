<?php
$pageTitle = isset($activo) ? 'Editar activo' : 'Nuevo activo';
require __DIR__ . '/../../layouts/header.php';
$errors = $_SESSION['form_errors'] ?? [];
$old = $_SESSION['form_old'] ?? ($activo ?? []);
unset($_SESSION['form_errors'], $_SESSION['form_old']);
$actionUrl = isset($activo) ? base_url('/activos/' . $activo['id']) : base_url('/activos');
?>
<h2 class="mb-3"><?= e($pageTitle) ?></h2>

<form method="post" action="<?= e($actionUrl) ?>" class="card p-4" style="max-width: 640px;">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label">Nombre *</label>
        <input type="text" name="nombre" class="form-control <?= isset($errors['nombre']) ? 'is-invalid' : '' ?>" value="<?= e($old['nombre'] ?? '') ?>" required>
        <?php if (isset($errors['nombre'])): ?><div class="invalid-feedback"><?= e($errors['nombre']) ?></div><?php endif; ?>
    </div>

    <div class="mb-3">
        <label class="form-label">Tipo</label>
        <input type="text" name="tipo" class="form-control" value="<?= e($old['tipo'] ?? '') ?>" placeholder="PLC, CNC, contactor...">
    </div>

    <div class="mb-3">
        <label class="form-label">Planta *</label>
        <select name="planta_id" class="form-select <?= isset($errors['planta_id']) ? 'is-invalid' : '' ?>" required>
            <option value="">-- Seleccione --</option>
            <?php foreach ($plantas as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) ($old['planta_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['planta_id'])): ?><div class="invalid-feedback"><?= e($errors['planta_id']) ?></div><?php endif; ?>
    </div>

    <div class="mb-3">
        <label class="form-label">Ubicación</label>
        <input type="text" name="ubicacion" class="form-control" value="<?= e($old['ubicacion'] ?? '') ?>">
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Dirección IP</label>
            <input type="text" name="ip_address" class="form-control <?= isset($errors['ip_address']) ? 'is-invalid' : '' ?>" value="<?= e($old['ip_address'] ?? '') ?>" placeholder="192.168.1.10">
            <?php if (isset($errors['ip_address'])): ?><div class="invalid-feedback"><?= e($errors['ip_address']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Nombre PROFINET</label>
            <input type="text" name="nombre_profinet" class="form-control" value="<?= e($old['nombre_profinet'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Versión de firmware</label>
            <input type="text" name="version_firmware" class="form-control" value="<?= e($old['version_firmware'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Fecha puesta en marcha</label>
            <input type="date" name="fecha_puesta_marcha" class="form-control" value="<?= e($old['fecha_puesta_marcha'] ?? '') ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select">
            <?php foreach (['operativo', 'en_mantenimiento', 'fuera_servicio'] as $estado): ?>
                <option value="<?= $estado ?>" <?= ($old['estado'] ?? 'operativo') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="<?= base_url('/activos') ?>" class="btn btn-outline-secondary">Cancelar</a>
</form>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
