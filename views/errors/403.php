<?php $pageTitle = 'Acceso denegado'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="text-center py-5">
    <h1 class="display-4">403</h1>
    <p class="lead">No tiene permisos para acceder a esta sección.</p>
    <a href="<?= base_url('/dashboard') ?>" class="btn btn-primary">Volver al inicio</a>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
