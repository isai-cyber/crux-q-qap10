<?php $pageTitle = 'Crear cuenta'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="auth-wrap">
    <a href="<?= base_url('/') ?>" class="auth-brand">
        <span class="brand-tile"><img src="<?= base_url('/img/logo.png') ?>" alt="Logo"></span>
        <span class="brand-wordmark">Crux A.I<small>INDUSTRIAL HUB</small></span>
    </a>
    <div class="auth-card">
        <h1>Crear tu cuenta</h1>
        <p class="auth-sub">Empieza gratis con tu organización.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('/registro') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Nombre de tu organización</label>
                <input type="text" name="organizacion" class="form-control" value="<?= e($old['organizacion'] ?? '') ?>" placeholder="Ej: Mantenimiento Industrial S.A." required>
            </div>
            <div class="mb-3">
                <label class="form-label">Tu nombre</label>
                <input type="text" name="nombre" class="form-control" value="<?= e($old['nombre'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input type="text" name="usuario" class="form-control" value="<?= e($old['usuario'] ?? '') ?>" minlength="3" required autocapitalize="off">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Crear cuenta</button>
        </form>
        <p class="auth-alt mb-0">
            ¿Ya tienes cuenta? <a href="<?= base_url('/login') ?>">Inicia sesión</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
