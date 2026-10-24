<?php $pageTitle = 'Reportes'; require __DIR__ . '/../../layouts/header.php'; ?>

<h2 class="mb-3">Reportes</h2>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                Tickets por estado
                <a href="<?= base_url('/reportes/tickets/exportar') ?>" class="btn btn-sm btn-outline-secondary">Exportar CSV</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($porEstado as $row): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($row['estado']) ?></span>
                        <strong><?= (int) $row['total'] ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Tickets por planta</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($porPlanta as $row): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($row['planta']) ?></span>
                        <strong><?= (int) $row['total'] ?></strong>
                    </li>
                <?php endforeach; ?>
                <?php if (!$porPlanta): ?>
                    <li class="list-group-item text-secondary">Sin datos suficientes.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Costos acumulados</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span>Mano de obra</span><strong>$<?= number_format($costoManoObra, 2) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Repuestos</span><strong>$<?= number_format($costoRepuestosTotal, 2) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between bg-light">
                    <span class="fw-bold">Total</span><strong>$<?= number_format($costoManoObra + $costoRepuestosTotal, 2) ?></strong>
                </li>
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Activos con más tiempo de inactividad</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($downtimePorActivo as $row): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($row['nombre']) ?></span>
                        <strong><?= number_format((float) $row['horas'], 1) ?> h</strong>
                    </li>
                <?php endforeach; ?>
                <?php if (!$downtimePorActivo): ?>
                    <li class="list-group-item text-secondary">Sin registros de inactividad aún.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">Repuestos más consumidos</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>N° Parte</th><th>Descripción</th><th>Total consumido</th></tr></thead>
                    <tbody>
                    <?php foreach ($consumoRepuestos as $row): ?>
                        <tr>
                            <td><code><?= e($row['numero_parte']) ?></code></td>
                            <td><?= e($row['descripcion']) ?></td>
                            <td><?= (int) $row['total_consumido'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$consumoRepuestos): ?>
                        <tr><td colspan="3" class="text-center text-secondary py-3">Sin movimientos de stock aún.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
