<?php $pageTitle = 'Dashboard'; require __DIR__ . '/../layouts/header.php'; ?>

<?php
// Pre-cálculos para las gráficas.
$maxDia = 0;
foreach ($ticketsPorDia as $fila) {
    $maxDia = max($maxDia, (int) $fila['total']);
}
$coloresPrioridad = [
    'critica' => 'var(--accent-rose)',
    'alta'    => 'var(--accent-amber)',
    'media'   => '#CA8A04',
    'baja'    => 'var(--text-light)',
];
$maxPrioridad = max(1, max($ticketsPorPrioridad));

$maxEstado = 0;
foreach ($porEstado as $fila) {
    $maxEstado = max($maxEstado, (int) $fila['total']);
}

// Progreso operativo: % de tickets gestionados (no abiertos) sobre el total.
$gestionados = 0;
$abiertosPuros = 0;
foreach ($porEstado as $fila) {
    if (in_array($fila['estado'], ['en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'], true)) {
        $gestionados += (int) $fila['total'];
    }
    if ($fila['estado'] === 'abierto') {
        $abiertosPuros = (int) $fila['total'];
    }
}
$progresoGestion = $totalTickets > 0 ? round(($abiertosPuros + $gestionados) > 0 ? ($gestionados / $totalTickets * 100) : 0, 1) : 0.0;
?>

<h2 class="mb-4">Dashboard</h2>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-secondary small fw-semibold">Tickets abiertos</span>
                    <span class="kpi-icon azul"><i class="bi bi-ticket-detailed"></i></span>
                </div>
                <div class="kpi-value"><?= (int) $kpis['tickets_abiertos'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-secondary small fw-semibold">Críticos activos</span>
                    <span class="kpi-icon rojo"><i class="bi bi-exclamation-triangle"></i></span>
                </div>
                <div class="kpi-value" style="color: var(--accent-rose);"><?= (int) $kpis['tickets_criticos'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-secondary small fw-semibold">Fuera de servicio</span>
                    <span class="kpi-icon ambar"><i class="bi bi-hdd-network"></i></span>
                </div>
                <div class="kpi-value"><?= (int) $kpis['activos_fuera_servicio'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-secondary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-secondary small fw-semibold">Sin stock</span>
                    <span class="kpi-icon gris"><i class="bi bi-box-seam"></i></span>
                </div>
                <div class="kpi-value"><?= (int) $kpis['productos_sin_stock'] ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Fila analítica: columnas por día + prioridades + progreso -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up-arrow text-primary"></i> Tickets por día</span>
                <span class="badge bg-light">Últimos 14 días</span>
            </div>
            <div class="card-body">
                <?php if ($maxDia === 0): ?>
                    <p class="text-secondary small mb-0">Sin actividad reciente.</p>
                <?php else: ?>
                    <div class="chart-cols">
                        <?php foreach ($ticketsPorDia as $fila): ?>
                            <?php $esMax = (int) $fila['total'] === $maxDia && $maxDia > 0; ?>
                            <div class="chart-col-item" title="<?= e($fila['dia']) ?>: <?= (int) $fila['total'] ?> tickets">
                                <span class="chart-col-value <?= $esMax ? 'max' : '' ?>"><?= (int) $fila['total'] ?></span>
                                <div class="chart-col-track">
                                    <div class="chart-col-fill <?= $esMax ? 'max' : '' ?>"
                                         style="height: <?= max(4, (int) round(((int) $fila['total']) / $maxDia * 100)) ?>%;"></div>
                                </div>
                                <span class="chart-col-label"><?= e(date('d', strtotime($fila['dia']))) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-flag text-primary"></i> Por prioridad</div>
            <div class="card-body">
                <?php if ($totalTickets === 0): ?>
                    <p class="text-secondary small mb-0">Sin tickets aún.</p>
                <?php else: ?>
                    <div class="chart-hbar">
                        <?php foreach (['critica', 'alta', 'media', 'baja'] as $prioridad): ?>
                            <div class="chart-hbar-row">
                                <span class="chart-hbar-label"><?= e(ucfirst($prioridad)) ?></span>
                                <span class="chart-hbar-track">
                                    <span class="chart-hbar-fill"
                                          style="width: <?= max(4, (int) round($ticketsPorPrioridad[$prioridad] / $maxPrioridad * 100)) ?>%; background: <?= $coloresPrioridad[$prioridad] ?>;"></span>
                                </span>
                                <span class="chart-hbar-value"><?= (int) $ticketsPorPrioridad[$prioridad] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-speedometer text-primary"></i> Progreso</div>
            <div class="card-body d-flex flex-column justify-content-center gap-3">
                <div class="progress-ring">
                    <svg width="92" height="92" viewBox="0 0 92 92" class="progress-ring-svg">
                        <circle class="progress-ring-bg" cx="46" cy="46" r="38" stroke-width="9"/>
                        <circle class="progress-ring-fill" cx="46" cy="46" r="38" stroke-width="9"
                                stroke-dasharray="<?= 2 * M_PI * 38 ?>"
                                stroke-dashoffset="<?= round(2 * M_PI * 38 * (1 - $tasaCierre / 100)) ?>"/>
                        <text class="progress-ring-text" x="46" y="44" text-anchor="middle"><?= e($tasaCierre) ?>%</text>
                        <text class="progress-ring-sub" x="46" y="60" text-anchor="middle">Cerrados</text>
                    </svg>
                    <div class="small text-secondary">
                        <strong class="text-dark d-block"><?= (int) $totalTickets ?></strong>
                        tickets totales en el sistema
                    </div>
                </div>
                <div class="progress-h">
                    <div class="progress-h-header">
                        <span class="progress-h-label">Gestión avanzada</span>
                        <span class="progress-h-value"><?= e($progresoGestion) ?>%</span>
                    </div>
                    <div class="progress-h-track">
                        <div class="progress-h-fill" style="width: <?= $progresoGestion ?>%; background: linear-gradient(90deg, var(--brand-cyan), var(--brand-blue));"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Tickets recientes</span>
                <a href="<?= base_url('/tickets/nuevo') ?>" class="btn btn-sm btn-primary">+ Nuevo ticket</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Folio</th><th>Activo</th><th>Descripción</th><th>Prioridad IA</th><th>Estado</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ticketsRecientes as $t): ?>
                        <tr>
                            <td class="text-mono small"><?= e($t['folio']) ?></td>
                            <td><?= e($t['activo_nombre'] ?? '—') ?></td>
                            <td><?= e(mb_strimwidth($t['descripcion'], 0, 60, '…')) ?></td>
                            <td>
                                <?php if ($t['prioridad_ia']): ?>
                                    <span class="badge badge-prioridad-<?= e($t['prioridad_ia']) ?>"><?= e($t['prioridad_ia']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light"><?= e($t['estado']) ?></span></td>
                            <td><a href="<?= base_url('/tickets/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ticketsRecientes): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-3">Sin tickets registrados aún.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Tickets por estado</div>
            <div class="card-body">
                <?php if ($maxEstado === 0): ?>
                    <p class="text-secondary small mb-0">Sin tickets registrados aún.</p>
                <?php else: ?>
                    <div class="barlist">
                        <?php foreach ($porEstado as $fila): ?>
                            <div class="barlist-row">
                                <span class="barlist-label"><?= e(ucfirst(str_replace('_', ' ', $fila['estado']))) ?></span>
                                <span class="barlist-value"><?= (int) $fila['total'] ?></span>
                                <span class="barlist-track">
                                    <span class="barlist-fill" style="width: <?= max(4, (int) round(((int) $fila['total']) / $maxEstado * 100)) ?>%;"></span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

