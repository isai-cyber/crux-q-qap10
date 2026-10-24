<?php $pageTitle = 'Activos'; require __DIR__ . '/../../layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Activos (CMDB)</h2>
    <a href="<?= base_url('/activos/nuevo') ?>" class="btn btn-primary">+ Nuevo activo</a>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, IP, nombre PROFINET..." value="<?= e($_GET['q'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Buscar</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Nombre</th><th>Planta</th><th>IP</th><th>Firmware</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultado['items'] as $a): ?>
                <tr>
                    <td><?= e($a['nombre']) ?></td>
                    <td><?= e($a['planta_nombre']) ?></td>
                    <td><?= e($a['ip_address'] ?? '—') ?></td>
                    <td><?= e($a['version_firmware'] ?? '—') ?></td>
                    <td><span class="badge bg-light text-dark"><?= e($a['estado']) ?></span></td>
                    <td><a href="<?= base_url('/activos/' . $a['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$resultado['items']): ?>
                <tr><td colspan="6" class="text-center text-secondary py-3">Sin activos registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$totalPages = (int) ceil($resultado['total'] / $resultado['per_page']);
if ($totalPages > 1):
?>
<nav class="mt-3">
    <ul class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $resultado['page'] ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($_GET['q'] ?? '') ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
