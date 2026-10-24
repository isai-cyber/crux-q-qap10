<?php $pageTitle = 'Runbooks'; require __DIR__ . '/../../layouts/header.php'; ?>

<h2 class="mb-3">Runbooks (procedimientos de solución autoevolutivos)</h2>
<p class="text-secondary">
    Se generan solos a partir de tickets resueltos. Cada vez que un técnico marca si la
    solución funcionó, el runbook ajusta su tasa de éxito: con 3+ aplicaciones y ≥70% de
    éxito se <strong>valida</strong> automáticamente; con ≥50% de fallo se <strong>retira</strong>
    y deja de sugerirse — sin que nadie edite nada a mano.
</p>

<div class="row g-3">
    <?php foreach ($runbooks as $r): $total = (int) $r['veces_aplicado']; $tasa = $total > 0 ? round($r['veces_exitoso'] / $total * 100) : null; ?>
        <div class="col-md-6">
            <div class="card h-100 <?= !$r['vigente'] ? 'border-danger' : '' ?>">
                <div class="card-body">
                    <h5 class="card-title"><?= e($r['titulo']) ?></h5>
                    <p class="small text-secondary mb-1">Activo: <?= e($r['activo_nombre'] ?? 'General') ?></p>
                    <p><strong>Síntoma:</strong> <?= e($r['sintoma']) ?></p>
                    <p><strong>Solución:</strong> <?= e($r['solucion']) ?></p>

                    <span class="badge <?= $r['validado'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $r['validado'] ? 'Validado' : 'Sugerido' ?>
                    </span>
                    <?php if (!$r['vigente']): ?>
                        <span class="badge bg-danger">Retirado automáticamente</span>
                    <?php endif; ?>
                    <span class="badge bg-light text-dark"><?= e($r['generado_por']) ?></span>
                    <span class="badge bg-light text-dark">
                        <?= $total ?> aplicaciones<?= $tasa !== null ? " · {$tasa}% éxito" : '' ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$runbooks): ?>
        <p class="text-secondary">Aún no hay runbooks registrados. Se crean automáticamente al completar órdenes de trabajo.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
