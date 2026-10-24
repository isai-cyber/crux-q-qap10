<?php $pageTitle = 'Usuarios'; require __DIR__ . '/../../layouts/header.php'; $errors = $_SESSION['form_errors'] ?? []; unset($_SESSION['form_errors']); ?>

<h2 class="mb-3">Usuarios</h2>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Activo</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= e($u['nombre']) ?></td>
                            <td><code><?= e($u['usuario']) ?></code></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= e($u['rol']) ?></span></td>
                            <td>
                                <form method="post" action="<?= base_url('/admin/usuarios/' . $u['id']) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="activo" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="activo" value="1" onchange="this.form.submit()" <?= $u['activo'] ? 'checked' : '' ?>>
                                    </div>
                                </form>
                            </td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header">Nuevo usuario</div>
            <div class="card-body">
                <form method="post" action="<?= base_url('/admin/usuarios') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Usuario</label>
                        <input type="text" name="usuario" class="form-control" minlength="3" required autocapitalize="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Rol</label>
                        <select name="rol" class="form-select">
                            <option value="tecnico">Técnico</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Planta</label>
                        <select name="planta_id" class="form-select">
                            <option value="">-- Sin asignar --</option>
                            <?php foreach ($plantas as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Crear usuario</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
