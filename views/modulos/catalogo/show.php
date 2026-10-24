<?php $pageTitle = $producto['numero_parte']; require __DIR__ . '/../../layouts/header.php'; ?>

<h2 class="mb-3"><?= e($producto['numero_parte']) ?></h2>

<div class="card" style="max-width: 640px;">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-4">Descripción</dt><dd class="col-8"><?= e($producto['descripcion']) ?></dd>
            <dt class="col-4">Categoría</dt><dd class="col-8"><?= e($producto['categoria_nombre'] ?? '—') ?></dd>
            <dt class="col-4">Stock actual</dt><dd class="col-8"><?= (int) $producto['stock_actual'] ?></dd>
            <dt class="col-4">Precio de lista</dt><dd class="col-8"><?= $producto['precio_lista'] !== null ? '$' . number_format((float) $producto['precio_lista'], 2) : '—' ?></dd>
            <dt class="col-4">Vigencia catálogo</dt><dd class="col-8"><?= e($producto['vigencia_catalogo'] ?? '—') ?></dd>
            <dt class="col-4">Origen</dt><dd class="col-8"><?= e($producto['origen_documento'] ?? '—') ?></dd>
        </dl>
        <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-secondary small">Pedido & Pago Inmediato:</span>
            <a href="<?= base_url('/suscripcion/checkout?tipo=repuesto&id=' . $producto['id']) ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2">
                <i class="bi bi-stripe"></i> Comprar con Stripe ($<?= number_format((float) ($producto['precio_lista'] ?? 45.00), 2) ?>)
            </a>
        </div>
    </div>
</div>

<a href="<?= base_url('/catalogo') ?>" class="btn btn-outline-secondary mt-3">Volver al catálogo</a>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
