<?php
$pageTitle = 'Órdenes de Compra';
require __DIR__ . '/../../layouts/header.php';
$rol = Auth::user()['rol'];
$puedeAprobar = in_array($rol, ['admin', 'supervisor'], true);

$badges = [
    'pendiente' => 'bg-warning',
    'aprobada' => 'bg-info text-dark',
    'rechazada' => 'bg-danger',
    'recibida' => 'bg-success',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-1">Órdenes de Compra</h2>
        <p class="text-secondary mb-0 small">
            Reabastecimiento de inventario con aprobación: solicitar → aprobar/rechazar →
            recibir. El stock solo se actualiza al recibir la orden.
        </p>
    </div>
    <a href="<?= base_url('/ordenes-compra/nueva') ?>" class="btn btn-primary">+ Solicitar compra</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Folio</th><th>Producto</th><th>Cantidad</th><th>Solicitó</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($ordenes as $o): ?>
                <tr>
                    <td class="text-mono small"><?= e($o['folio']) ?></td>
                    <td><?= e($o['numero_parte']) ?> <small class="text-secondary d-block"><?= e($o['producto_descripcion']) ?></small></td>
                    <td><?= (int) $o['cantidad'] ?></td>
                    <td><?= e($o['solicitante_nombre'] ?? '—') ?></td>
                    <td><span class="badge <?= $badges[$o['estado']] ?? 'bg-light text-dark' ?>"><?= e(ucfirst($o['estado'])) ?></span></td>
                    <td class="text-end">
                        <?php if ($puedeAprobar && $o['estado'] === 'pendiente'): ?>
                            <form method="post" action="<?= base_url('/ordenes-compra/' . $o['id'] . '/aprobar') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success">Aprobar</button>
                            </form>
                            <form method="post" action="<?= base_url('/ordenes-compra/' . $o['id'] . '/rechazar') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">Rechazar</button>
                            </form>
                        <?php elseif ($puedeAprobar && $o['estado'] === 'aprobada'): ?>
                            <form method="post" action="<?= base_url('/ordenes-compra/' . $o['id'] . '/recibir') ?>" class="d-inline" onsubmit="return confirm('¿Confirmas que llegó la mercancía? Esto sumará <?= (int) $o['cantidad'] ?> unidades al inventario.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-primary">Marcar recibida</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ordenes): ?>
                <tr><td colspan="6" class="text-center text-secondary py-3">Aún no hay órdenes de compra.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
