<?php
$pageTitle = 'Nuevo diseño técnico';
require __DIR__ . '/../../layouts/header.php';
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);
$aiConfigurada = (new AIService())->isConfigured();
?>
<h2 class="mb-3">Nuevo diseño técnico con IA</h2>

<?php if (!$aiConfigurada): ?>
    <div class="alert alert-warning">
        No hay <code>AI_API_KEY</code> configurada en <code>.env</code>: se generará un
        contenido de respaldo indicando que falta la clave, en vez de un diseño real.
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="<?= base_url('/disenos') ?>" class="card p-4" style="max-width: 700px;">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label">Tipo de diseño *</label>
        <select name="tipo" class="form-select" required>
            <option value="diagrama">Diagrama de control / cableado (SVG)</option>
            <option value="documento">Documento técnico (procedimiento, alcance, BOM)</option>
        </select>
    </div>

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
        <label class="form-label">Productos del catálogo a incluir (opcional, solo diagramas)</label>
        <select name="producto_ids[]" class="form-select" multiple size="5">
            <?php foreach ($productos as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['numero_parte']) ?> — <?= e($p['descripcion']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Ctrl/Cmd + clic para seleccionar varios.</div>
    </div>

    <div class="mb-3">
        <label class="form-label">Descripción de lo que necesitas *</label>
        <textarea name="descripcion_solicitud" class="form-control" rows="4" required
            placeholder="Ej: arranque directo con protección para motor trifásico 15kW/400V con paro de emergencia"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Generar con IA</button>
    <a href="<?= base_url('/disenos') ?>" class="btn btn-outline-secondary">Cancelar</a>
</form>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
