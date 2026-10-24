<?php $pageTitle = 'Tickets'; require __DIR__ . '/../../layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Tickets</h2>
    <a href="<?= base_url('/tickets/nuevo') ?>" class="btn btn-primary">+ Nuevo ticket</a>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-auto">
        <select name="estado" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            <?php foreach (['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'] as $estado): ?>
                <option value="<?= $estado ?>" <?= ($_GET['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Folio</th><th>Activo</th><th>Canal</th><th>Prioridad</th><th>Estado</th><th>Apertura</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($resultado['items'] as $t): ?>
                <tr>
                    <td><?= e($t['folio']) ?></td>
                    <td><?= e($t['activo_nombre'] ?? '—') ?></td>
                    <td><span class="badge bg-light text-dark"><?= e($t['canal_origen']) ?></span></td>
                    <td>
                        <?php $prioridad = $t['prioridad_final'] ?? $t['prioridad_ia']; ?>
                        <?php if ($prioridad): ?>
                            <span class="badge badge-prioridad-<?= e($prioridad) ?>"><?= e($prioridad) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark"><?= e($t['estado']) ?></span></td>
                    <td><?= e($t['fecha_apertura']) ?></td>
                    <td><a href="<?= base_url('/tickets/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$resultado['items']): ?>
                <tr><td colspan="7" class="text-center text-secondary py-3">Sin tickets.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
