<?php
$pageTitle = 'Nuevo ticket';
require __DIR__ . '/../../layouts/header.php';
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);
?>
<h2 class="mb-3">Reportar falla</h2>

<form method="post" action="<?= base_url('/tickets') ?>" class="card p-4" style="max-width: 640px;">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label">Activo relacionado</label>
        <select name="activo_id" class="form-select">
            <option value="">-- Sin especificar --</option>
            <?php foreach ($activos as $a): ?>
                <option value="<?= (int) $a['id'] ?>"><?= e($a['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Descripción de la falla *</label>
        <textarea name="descripcion" class="form-control <?= isset($errors['descripcion']) ? 'is-invalid' : '' ?>" rows="4" required placeholder="Ej: el contactor de la línea 2 no arranca"></textarea>
        <?php if (isset($errors['descripcion'])): ?><div class="invalid-feedback"><?= e($errors['descripcion']) ?></div><?php endif; ?>
        <div class="form-text">La prioridad y categoría se clasificarán automáticamente con IA al guardar.</div>
    </div>

    <button type="submit" class="btn btn-primary">Crear ticket</button>
    <a href="<?= base_url('/tickets') ?>" class="btn btn-outline-secondary">Cancelar</a>
</form>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
