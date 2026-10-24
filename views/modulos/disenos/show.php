<?php $pageTitle = $diseno['titulo']; require __DIR__ . '/../../layouts/header.php'; ?>

<h2 class="mb-1"><?= e($diseno['titulo']) ?></h2>
<p class="text-secondary">
    <span class="badge bg-<?= $diseno['tipo'] === 'diagrama' ? 'info' : 'secondary' ?> text-dark"><?= e($diseno['tipo']) ?></span>
    Activo: <?= e($diseno['activo_nombre'] ?? 'General') ?> · Generado: <?= e($diseno['created_at']) ?>
</p>

<div class="card mb-3">
    <div class="card-header">Solicitud original</div>
    <div class="card-body"><?= nl2br(e($diseno['descripcion_solicitud'])) ?></div>
</div>

<div class="card">
    <div class="card-header">Resultado</div>
    <div class="card-body">
        <?php if ($diseno['formato'] === 'svg'): ?>
            <img src="data:image/svg+xml;base64,<?= base64_encode($diseno['contenido']) ?>"
                 alt="Diagrama técnico generado por IA" class="img-fluid border rounded" style="max-width: 100%;">
        <?php else: ?>
            <pre class="border rounded p-3 bg-light" style="white-space: pre-wrap;"><?= e($diseno['contenido']) ?></pre>
        <?php endif; ?>
    </div>
</div>

<a href="<?= base_url('/disenos') ?>" class="btn btn-outline-secondary mt-3">Volver</a>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
