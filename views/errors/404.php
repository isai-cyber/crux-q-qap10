<?php $pageTitle = 'No encontrado'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="text-center py-5">
    <h1 class="display-4">404</h1>
    <p class="lead">La página solicitada no existe.</p>
    <a href="<?= base_url('/dashboard') ?>" class="btn btn-primary">Volver al inicio</a>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
