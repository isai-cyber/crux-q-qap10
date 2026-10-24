<?php $pageTitle = 'Mantenimiento Preventivo'; require __DIR__ . '/../../layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-1">Mantenimiento Preventivo</h2>
        <p class="text-secondary mb-0 small">
            Tareas recurrentes por calendario sobre un activo. Cuando se cumple la fecha,
            se genera automáticamente un ticket preventivo y su orden de trabajo — sin
            esperar a que algo falle.
        </p>
    </div>
    <a href="<?= base_url('/mantenimiento-preventivo/nuevo') ?>" class="btn btn-primary">+ Nuevo plan</a>
</div>

<?php if (!empty($generados)): ?>
    <div class="alert alert-success">
        Se generaron <?= count($generados) ?> ticket(s) preventivo(s) automáticamente al cargar esta página:
        <?php foreach ($generados as $t): ?>
            <a href="<?= base_url('/tickets/' . $t['id']) ?>" class="alert-link"><?= e($t['folio']) ?></a><?= $t !== end($generados) ? ', ' : '' ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Plan</th><th>Activo</th><th>Frecuencia</th><th>Próxima fecha</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($planes as $p): ?>
                <?php $vencido = strtotime($p['proxima_fecha']) <= strtotime(date('Y-m-d')); ?>
                <tr>
                    <td>
                        <strong><?= e($p['titulo']) ?></strong>
                        <?php if ($p['descripcion']): ?><br><small class="text-secondary"><?= e($p['descripcion']) ?></small><?php endif; ?>
                    </td>
                    <td><?= e($p['activo_nombre']) ?></td>
                    <td>Cada <?= (int) $p['frecuencia_dias'] ?> día(s)</td>
                    <td><?= e($p['proxima_fecha']) ?></td>
                    <td>
                        <span class="badge <?= $vencido ? 'bg-warning' : 'bg-light text-dark' ?>">
                            <?= $vencido ? 'Vencido (se genera al recargar)' : 'Programado' ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" action="<?= base_url('/mantenimiento-preventivo/' . $p['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Desactivar este plan de mantenimiento?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$planes): ?>
                <tr><td colspan="6" class="text-center text-secondary py-3">Aún no hay planes de mantenimiento preventivo.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
