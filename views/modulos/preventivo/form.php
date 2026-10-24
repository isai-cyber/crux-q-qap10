<?php
$pageTitle = 'Nuevo plan de mantenimiento preventivo';
require __DIR__ . '/../../layouts/header.php';
$errors = $_SESSION['form_errors'] ?? [];
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);
?>

<h2 class="mb-3">Nuevo plan de mantenimiento preventivo</h2>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="<?= base_url('/mantenimiento-preventivo') ?>" class="card p-4" style="max-width: 640px;">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label">Título *</label>
        <input type="text" name="titulo" class="form-control" value="<?= e($old['titulo'] ?? '') ?>" placeholder="Ej: Lubricación de rodamientos" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalle del procedimiento (opcional)"><?= e($old['descripcion'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Activo *</label>
        <select name="activo_id" class="form-select" required>
            <option value="">-- Seleccione --</option>
            <?php foreach ($activos as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= (int) ($old['activo_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row">
        <div class="col-6 mb-3">
            <label class="form-label">Frecuencia (días) *</label>
            <input type="number" name="frecuencia_dias" class="form-control" min="1" value="<?= e($old['frecuencia_dias'] ?? '30') ?>" required>
        </div>
        <div class="col-6 mb-3">
            <label class="form-label">Próxima fecha *</label>
            <input type="date" name="proxima_fecha" class="form-control" value="<?= e($old['proxima_fecha'] ?? date('Y-m-d')) ?>" required>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Crear plan</button>
    <a href="<?= base_url('/mantenimiento-preventivo') ?>" class="btn btn-outline-secondary">Cancelar</a>
</form>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
