<?php $pageTitle = $ticket['folio']; require __DIR__ . '/../../layouts/header.php'; $usuario = Auth::user(); ?>

<h2 class="mb-3">Ticket <?= e($ticket['folio']) ?></h2>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card mb-3">
            <div class="card-header">Descripción del reporte</div>
            <div class="card-body">
                <p><?= nl2br(e($ticket['descripcion'])) ?></p>
                <div class="small text-secondary">
                    Activo: <?= e($ticket['activo_nombre'] ?? '—') ?> ·
                    Canal: <?= e($ticket['canal_origen']) ?> ·
                    Apertura: <?= e($ticket['fecha_apertura']) ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Clasificación por IA</div>
            <div class="card-body">
                <?php if ($ticket['prioridad_ia']): ?>
                    <p>
                        Categoría sugerida: <strong><?= e($ticket['categoria_ia'] ?? '—') ?></strong><br>
                        Prioridad sugerida: <span class="badge badge-prioridad-<?= e($ticket['prioridad_ia']) ?>"><?= e($ticket['prioridad_ia']) ?></span>
                    </p>
                <?php else: ?>
                    <p class="text-secondary">Este ticket no fue clasificado por IA.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header">Estado del ticket</div>
            <div class="card-body">
                <p>Estado actual: <span class="badge bg-light text-dark"><?= e($ticket['estado']) ?></span></p>
                <?php if (in_array($usuario['rol'], ['admin', 'supervisor', 'tecnico'], true)): ?>
                <form method="post" action="<?= base_url('/tickets/' . $ticket['id'] . '/estado') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Nuevo estado</label>
                        <select name="estado" class="form-select">
                            <?php foreach (['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'] as $estado): ?>
                                <option value="<?= $estado ?>" <?= $ticket['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Prioridad final</label>
                        <select name="prioridad_final" class="form-select">
                            <option value="">-- Mantener sugerida por IA --</option>
                            <?php foreach (['baja', 'media', 'alta', 'critica'] as $p): ?>
                                <option value="<?= $p ?>" <?= ($ticket['prioridad_final'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Actualizar</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
