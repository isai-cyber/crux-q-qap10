<?php 
$pageTitle = "API Keys & Integraciones"; 
require __DIR__ . "/../../layouts/header.php"; 
?>

<div class="container-fluid py-2">
    <!-- Header del módulo -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1 text-dark fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-key-fill text-primary"></i> Mis Llaves de API (Acceso Personal a <?= e($marcaNombre) ?>)
            </h3>
            <p class="text-secondary mb-0 small">
                Genera llaves de API (Bearer Tokens) para consumir los endpoints REST y conectar agentes externos (Claude, Hermes, bots de WhatsApp, scripts de telemetría y SCADA).
            </p>
        </div>
        <div>
            <span class="badge bg-light text-dark border px-3 py-2">
                <i class="bi bi-shield-lock-fill text-success"></i> Autenticación Bearer Token
            </span>
        </div>
    </div>

    <!-- Alerta de Nueva Llave Generada con Botón de Copiar -->
    <?php if ($tokenPlano): ?>
        <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border-left: 5px solid #16A34A !important;">
            <div class="card-body p-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="p-2 bg-success text-white rounded-3">
                        <i class="bi bi-check-lg fs-4"></i>
                    </div>
                    <div class="flex-fill">
                        <h5 class="fw-bold text-success mb-1">¡Nueva Llave de API generada con éxito!</h5>
                        <p class="text-secondary small mb-3">
                            Guarda esta clave en un lugar seguro. <strong>Por motivos de seguridad, no se volverá a mostrar en texto plano.</strong>
                        </p>
                        <div class="input-group mb-2" style="max-width: 720px;">
                            <input type="text" id="nuevaApiKeyInput" class="form-control font-monospace bg-white fw-bold text-dark" value="<?= e($tokenPlano) ?>" readonly>
                            <button class="btn btn-success fw-bold d-flex align-items-center gap-2" onclick="copiarApiKey()">
                                <i class="bi bi-clipboard-check"></i> <span id="btnCopiarTexto">Copiar Llave</span>
                            </button>
                        </div>
                        <small class="text-muted">Nombre asignado: <strong><?= e($tokenNombre ?? "API Key") ?></strong></small>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Tabla de Llaves Activas -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">Mis Llaves Personales</h5>
                    <span class="badge bg-secondary"><?= count($tokens) ?> Registradas</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Permisos Habilitados</th>
                                <th>Estado</th>
                                <th>Último Uso</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tokens as $t): $permisos = json_decode($t["permisos"], true) ?: []; ?>
                            <tr>
                                <td>
                                    <strong class="text-dark d-block"><?= e($t["nombre"]) ?></strong>
                                    <small class="text-muted">Creada: <?= e(substr($t["created_at"], 0, 10)) ?></small>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (count($permisos) >= 9): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-stars"></i> Acceso Total (Todas las funciones)</span>
                                        <?php else: ?>
                                            <?php foreach ($permisos as $p): ?>
                                                <span class="badge bg-light text-dark border"><?= e(str_replace("_", " ", $p)) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($t["activo"]): ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Revocada</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= e($t["ultimo_uso"] ?? "Nunca") ?></small></td>
                                <td class="text-end">
                                    <?php if ($t["activo"]): ?>
                                        <form method="post" action="<?= base_url("/admin/tokens/" . $t["id"] . "/revocar") ?>" onsubmit="return confirm('¿Estás seguro de revocar esta API Key? Los agentes que la utilicen perderán acceso inmediatamente.');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Revocar llave">
                                                <i class="bi bi-trash3"></i> Revocar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <small class="text-muted">Inactiva</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$tokens): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-secondary">
                                    <i class="bi bi-key fs-2 d-block text-muted mb-2"></i>
                                    No tienes llaves de API activas. Genera una en el panel lateral para comenzar.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Formulario para Generar Nueva Llave -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle-fill text-primary"></i> Generar Nueva Llave de API
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <form method="post" action="<?= base_url("/admin/tokens") ?>">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Nombre de la Llave o Integración</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Agente Claude Desktop / Servidor MCP / Bot WhatsApp" required autofocus>
                            <small class="text-muted">Un identificador para recordar dónde estás utilizando esta llave.</small>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-semibold text-dark mb-0">Permisos de Acceso al Programa</label>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="seleccionarTodosPermisos()">Seleccionar Todos</button>
                            </div>

                            <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2">
                                <?php 
                                $permisosLista = [
                                    "crear_ticket" => ["label" => "Crear tickets y triage automático con IA", "icon" => "bi-ticket-detailed"],
                                    "consultar_activos" => ["label" => "Consultar inventario de activos y diagnósticos", "icon" => "bi-hdd-network"],
                                    "crear_activo" => ["label" => "Dar de alta nuevos activos PROFINET/PLC", "icon" => "bi-plus-square"],
                                    "consultar_catalogo" => ["label" => "Buscar repuestos en catálogo Siemens SIRIUS", "icon" => "bi-boxes"],
                                    "consultar_alertas" => ["label" => "Vigilancia de alertas y fallas críticas en tiempo real", "icon" => "bi-bell"],
                                    "crear_usuario" => ["label" => "Gestión de técnicos y operarios de planta", "icon" => "bi-person-badge"],
                                    "consultar_tickets" => ["label" => "Consultar tickets y dashboard de la organización", "icon" => "bi-list-check"],
                                    "actualizar_ticket" => ["label" => "Actualizar estado de tickets (app móvil)", "icon" => "bi-pencil-square"],
                                    "usar_chat" => ["label" => "Conversar con el asistente ORION", "icon" => "bi-chat-dots"],
                                ];
                                ?>
                                <?php foreach ($permisosLista as $key => $info): ?>
                                    <div class="form-check">
                                        <input class="form-check-input perm-check" type="checkbox" name="permisos[]" value="<?= $key ?>" id="perm_<?= $key ?>" checked>
                                        <label class="form-check-label small text-dark d-flex align-items-center gap-2" for="perm_<?= $key ?>">
                                            <i class="bi <?= $info["icon"] ?> text-primary"></i> <?= e($info["label"]) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-key"></i> Generar Llave de API
                        </button>
                    </form>
                </div>
            </div>

            <!-- Mini Guía de Uso Rápido -->
            <div class="card shadow-sm border-0 mt-4 bg-light">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-code-slash text-primary"></i> Cómo autenticar tus peticiones:</h6>
                    <pre class="bg-dark text-white p-2 rounded-2 small font-monospace mb-0" style="font-size: 0.75rem;">curl -H "Authorization: Bearer crux_..."      http://localhost:8085/api/v1/alertas/pendientes</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copiarApiKey() {
    const input = document.getElementById("nuevaApiKeyInput");
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    
    const btnTexto = document.getElementById("btnCopiarTexto");
    btnTexto.textContent = "¡Copiada!";
    setTimeout(() => {
        btnTexto.textContent = "Copiar Llave";
    }, 2500);
}

function seleccionarTodosPermisos() {
    document.querySelectorAll(".perm-check").forEach(cb => cb.checked = true);
}
</script>

<?php require __DIR__ . "/../../layouts/footer.php"; ?>
