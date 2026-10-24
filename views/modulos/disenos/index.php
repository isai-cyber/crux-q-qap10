<?php $pageTitle = 'Diseños técnicos IA'; require __DIR__ . '/../../layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Diseños técnicos con IA</h2>
    <a href="<?= base_url('/disenos/nuevo') ?>" class="btn btn-primary">+ Nuevo diseño</a>
</div>
<p class="text-secondary">Diagramas de control/cableado y documentos técnicos generados por IA a partir de una descripción.</p>

<div class="row g-3">
    <?php foreach ($disenos as $d): ?>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <span class="badge bg-<?= $d['tipo'] === 'diagrama' ? 'info' : 'secondary' ?> text-dark mb-2"><?= e($d['tipo']) ?></span>
                    <h5 class="card-title"><?= e(mb_strimwidth($d['titulo'], 0, 60, '…')) ?></h5>
                    <p class="small text-secondary">Activo: <?= e($d['activo_nombre'] ?? 'General') ?> · <?= e($d['created_at']) ?></p>
                    <a href="<?= base_url('/disenos/' . $d['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$disenos): ?>
        <p class="text-secondary">Aún no se ha generado ningún diseño técnico.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
