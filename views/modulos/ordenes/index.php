<?php $pageTitle = 'Órdenes de trabajo'; require __DIR__ . '/../../layouts/header.php'; ?>

<h2 class="mb-3">Órdenes de trabajo</h2>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Ticket</th><th>Técnico</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ordenes as $o): ?>
                <tr>
                    <td><?= e($o['folio']) ?> — <?= e(mb_strimwidth($o['ticket_descripcion'], 0, 50, '…')) ?></td>
                    <td><?= e($o['tecnico_nombre'] ?? 'Sin asignar') ?></td>
                    <td><span class="badge bg-light text-dark"><?= e($o['estado']) ?></span></td>
                    <td><a href="<?= base_url('/ordenes/' . $o['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ordenes): ?>
                <tr><td colspan="4" class="text-center text-secondary py-3">Sin órdenes de trabajo.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
