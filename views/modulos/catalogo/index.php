<?php $pageTitle = 'Catálogo de productos'; require __DIR__ . '/../../layouts/header.php'; ?>

<h2 class="mb-3">Catálogo Siemens SIRIUS</h2>

<form class="row g-2 mb-3" method="get">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Número de parte o descripción..." value="<?= e($_GET['q'] ?? '') ?>">
    </div>
    <div class="col-md-3">
        <select name="categoria" class="form-select">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($_GET['categoria'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Buscar</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>N° Parte</th><th>Descripción</th><th>Categoría</th><th>Stock</th><th>Precio lista</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($resultado['items'] as $p): ?>
                <tr>
                    <td><code><?= e($p['numero_parte']) ?></code></td>
                    <td><?= e($p['descripcion']) ?></td>
                    <td><?= e($p['categoria_nombre'] ?? '—') ?></td>
                    <td><?= (int) $p['stock_actual'] === 0 ? '<span class="text-danger">0</span>' : (int) $p['stock_actual'] ?></td>
                    <td><?= $p['precio_lista'] !== null ? '$' . number_format((float) $p['precio_lista'], 2) : '—' ?></td>
                    <td><a href="<?= base_url('/catalogo/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$resultado['items']): ?>
                <tr><td colspan="6" class="text-center text-secondary py-3">Sin resultados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
