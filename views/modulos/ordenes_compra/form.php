<?php
$pageTitle = 'Solicitar compra';
require __DIR__ . '/../../layouts/header.php';
$errors = $_SESSION['form_errors'] ?? [];
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);
?>

<h2 class="mb-3">Solicitar compra</h2>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="<?= base_url('/ordenes-compra') ?>" class="card p-4" style="max-width: 640px;">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label">Producto *</label>
        <select name="producto_id" class="form-select" required>
            <option value="">-- Seleccione --</option>
            <?php foreach ($productos as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) ($old['producto_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                    <?= e($p['numero_parte']) ?> — <?= e($p['descripcion']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Cantidad *</label>
        <input type="number" name="cantidad" class="form-control" min="1" value="<?= e($old['cantidad'] ?? '1') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="3" placeholder="Ej: se acabó en la última reparación (opcional)"><?= e($old['notas'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Enviar solicitud</button>
    <a href="<?= base_url('/ordenes-compra') ?>" class="btn btn-outline-secondary">Cancelar</a>
</form>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
