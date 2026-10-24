<?php
$pageTitle = 'Iniciar sesión';
require __DIR__ . '/../layouts/header.php';

$brandLogoUrl = !empty($orgBranding['logo_path']) ? base_url('/uploads/logos/' . $orgBranding['logo_path']) : base_url('/img/logo.png');
$brandNombre = $orgBranding['nombre'] ?? 'Crux A.I';
?>
<div class="auth-wrap">
    <a href="<?= base_url('/') ?>" class="auth-brand">
        <span class="brand-tile"><img src="<?= e($brandLogoUrl) ?>" alt="Logo"></span>
        <?php if ($orgBranding): ?>
            <span class="brand-wordmark"><?= e($brandNombre) ?></span>
        <?php else: ?>
            <span class="brand-wordmark">Crux A.I<small>INDUSTRIAL HUB</small></span>
        <?php endif; ?>
    </a>
    <div class="auth-card">
        <h1>Iniciar sesión</h1>
        <p class="auth-sub">Accede al centro de mantenimiento de tu organización.</p>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= base_url('/login') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input type="text" name="usuario" class="form-control" required autofocus autocapitalize="off">
            </div>
            <div class="mb-4">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Entrar</button>
        </form>
        <p class="auth-alt mb-0">
            ¿Aún no tienes cuenta? <a href="<?= base_url('/registro') ?>">Crea tu organización</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
