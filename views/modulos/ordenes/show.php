<?php $pageTitle = 'Orden de trabajo'; require __DIR__ . '/../../layouts/header.php'; ?>

<h2 class="mb-3">Orden de trabajo — <?= e($orden['folio']) ?></h2>
<p class="text-secondary"><?= e($orden['ticket_descripcion']) ?></p>

<?php if ($runbookSugerido): ?>
    <div class="alert alert-info">
        <strong>Runbook sugerido automáticamente</strong> (síntoma similar a un caso ya resuelto):<br>
        <em><?= e($runbookSugerido['titulo']) ?></em> — <?= e($runbookSugerido['solucion']) ?>
        <div class="small text-secondary mt-1">
            <?= (int) $runbookSugerido['veces_exitoso'] ?> aplicaciones exitosas de <?= (int) $runbookSugerido['veces_aplicado'] ?>
            · <?= $runbookSugerido['validado'] ? 'Validado' : 'Aún sugerido' ?>
        </div>
    </div>
<?php endif; ?>

<?php $costoTotal = (float) ($orden['costo_mano_obra'] ?? 0) + $costoRepuestos; ?>
<div class="card p-3 mb-3" style="max-width: 640px;">
    <h6 class="mb-2">Costo de esta orden</h6>
    <div class="d-flex justify-content-between small">
        <span>Mano de obra</span><span>$<?= number_format((float) ($orden['costo_mano_obra'] ?? 0), 2) ?></span>
    </div>
    <div class="d-flex justify-content-between small">
        <span>Repuestos (<?= count($repuestosUsados) ?>)</span><span>$<?= number_format($costoRepuestos, 2) ?></span>
    </div>
    <hr class="my-2">
    <div class="d-flex justify-content-between fw-bold">
        <span>Total</span><span>$<?= number_format($costoTotal, 2) ?></span>
    </div>
    <?php if ($repuestosUsados): ?>
        <ul class="small text-secondary mt-2 mb-0">
            <?php foreach ($repuestosUsados as $r): ?>
                <li><?= (int) $r['cantidad'] ?>x <?= e($r['numero_parte']) ?> — $<?= number_format((float) ($r['precio_lista'] ?? 0), 2) ?> c/u</li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<form method="post" action="<?= base_url('/ordenes/' . $orden['id']) ?>" class="card p-4" style="max-width: 640px;">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label">Técnico asignado</label>
        <select name="tecnico_id" class="form-select">
            <option value="">-- Sin asignar --</option>
            <?php foreach ($tecnicos as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= (int) ($orden['tecnico_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Runbook aplicado</label>
        <select name="runbook_id" class="form-select">
            <option value="">-- Ninguno / solución nueva --</option>
            <?php foreach ($runbooksDisponibles as $r): ?>
                <option value="<?= (int) $r['id'] ?>" <?= (int) ($orden['runbook_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['titulo']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Si no seleccionas ninguno y escribes una solución, se creará un runbook nuevo automáticamente al completar.</div>
    </div>

    <div class="mb-3">
        <label class="form-label">Diagnóstico</label>
        <textarea name="diagnostico" class="form-control" rows="3"><?= e($orden['diagnostico'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Solución aplicada</label>
        <textarea name="solucion_aplicada" class="form-control" rows="3"><?= e($orden['solucion_aplicada'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">¿La solución funcionó?</label>
        <select name="solucion_exitosa" class="form-select">
            <option value="" <?= is_null($orden['solucion_exitosa']) ? 'selected' : '' ?>>-- Aún sin confirmar --</option>
            <option value="1" <?= $orden['solucion_exitosa'] === 1 ? 'selected' : '' ?>>Sí, resolvió el problema</option>
            <option value="0" <?= $orden['solucion_exitosa'] === 0 ? 'selected' : '' ?>>No, el problema persistió</option>
        </select>
        <div class="form-text">Esta respuesta alimenta la autoevolución: valida o retira el runbook usado.</div>
    </div>

    <div class="mb-3">
        <label class="form-label">Tiempo invertido (minutos)</label>
        <input type="number" name="tiempo_invertido_minutos" class="form-control" min="0" value="<?= e((string) ($orden['tiempo_invertido_minutos'] ?? '')) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Costo de mano de obra ($)</label>
        <input type="number" step="0.01" name="costo_mano_obra" class="form-control" min="0" value="<?= e((string) ($orden['costo_mano_obra'] ?? '')) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select">
            <?php foreach (['pendiente', 'en_progreso', 'completada'] as $estado): ?>
                <option value="<?= $estado ?>" <?= $orden['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Al marcar "completada" el ticket se cierra y se aplica la retroalimentación al runbook (o se crea uno nuevo).</div>
    </div>

    <button type="submit" class="btn btn-primary">Guardar</button>
</form>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
