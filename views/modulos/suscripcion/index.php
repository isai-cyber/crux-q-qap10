<?php
$pageTitle = "Suscripción y Pagos Stripe";
require __DIR__ . "/../../layouts/header.php";

$planActual = strtolower($org["plan"] ?? "trial");
$esAdmin = (Auth::user()["rol"] ?? "") === "admin";
?>

<div class="container-fluid py-2">
    <!-- Header del módulo -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1 text-dark fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-credit-card-2-front text-primary"></i> Planes, Suscripción & Pagos Stripe
            </h3>
            <p class="text-secondary mb-0 small">
                Gestiona los 3 niveles de servicio de tu micro SaaS ($0, $147 y $997), prueba pasarelas de pago y emite reembolsos.
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($esAdmin && $planActual !== "enterprise"): ?>
                <form method="post" action="<?= base_url("/suscripcion/activar-enterprise") ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 fw-bold">
                        <i class="bi bi-rocket-takeoff-fill"></i> Activar Plan Enterprise ($997)
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($esAdmin && !empty($org["stripe_customer_id"]) && !str_starts_with($org["stripe_customer_id"], "cus_sim_")): ?>
                <a href="<?= base_url("/suscripcion/portal") ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-up-right"></i> Portal Stripe
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alertas -->
    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= e($successMsg) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 py-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= e($errorMsg) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Estado Actual de la Organización -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded-3 bg-light text-primary">
                    <i class="bi bi-building-gear fs-3"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold">Organización: <?= e($org["nombre"]) ?></h6>
                    <small class="text-secondary">
                        ID: #<?= (int)$org["id"] ?> &bull; 
                        Estado Suscripción: <strong><?= e(ucfirst($org["stripe_subscription_status"] ?? ($planActual === "trial" ? "Trial Activo" : "Activo"))) ?></strong>
                    </small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <small class="text-muted d-block">Nivel de Servicio:</small>
                    <?php if ($planActual === "enterprise"): ?>
                        <span class="badge bg-primary px-3 py-2 fs-6 fw-bold"><i class="bi bi-stars"></i> ENTERPRISE GLOBAL ($997/mes)</span>
                    <?php elseif ($planActual === "pro"): ?>
                        <span class="badge bg-success px-3 py-2 fs-6 fw-bold"><i class="bi bi-lightning-fill"></i> INDUSTRIAL PRO ($147/mes)</span>
                    <?php else: ?>
                        <span class="badge bg-secondary px-3 py-2 fs-6 fw-bold">TRIAL INICIAL ($0)</span>
                    <?php endif; ?>
                </div>
                <?php if ($esAdmin && $planActual !== "trial"): ?>
                    <form method="post" action="<?= base_url("/suscripcion/cancelar") ?>" onsubmit="return confirm('¿Deseas cancelar la suscripción y volver al plan Trial ($0)?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Comparador de los 3 Planes -->
    <h5 class="fw-bold text-dark mb-3">Planes de Suscripción Disponibles</h5>
    <div class="row g-4 mb-4">
        <!-- Plan 1: $0 -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100 <?= ($planActual === "trial") ? "border border-2 border-secondary" : "" ?>">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-dark mb-0">Taller Inicial</h5>
                            <?php if ($planActual === "trial"): ?>
                                <span class="badge bg-secondary">PLAN ACTUAL</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-secondary small mb-3">Ideal para talleres y evaluación del sistema.</p>
                        <div class="mb-3">
                            <span class="display-6 fw-bold text-dark">$0</span>
                            <span class="text-muted small">/ mes (Demo)</span>
                        </div>
                        <hr>
                        <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary">
                            <li><i class="bi bi-check-lg text-success me-2"></i> Hasta 50 Activos Industriales</li>
                            <li><i class="bi bi-check-lg text-success me-2"></i> 1 Planta de Producción</li>
                            <li><i class="bi bi-check-lg text-success me-2"></i> Triage IA Básico</li>
                            <li><i class="bi bi-check-lg text-success me-2"></i> Catálogo Siemens SIRIUS</li>
                            <li><i class="bi bi-check-lg text-success me-2"></i> Hasta 3 Usuarios</li>
                        </ul>
                    </div>
                    <div class="mt-4">
                        <?php if ($planActual === "trial"): ?>
                            <button class="btn btn-light w-100 text-muted disabled">Plan Activo</button>
                        <?php elseif ($esAdmin): ?>
                            <form method="post" action="<?= base_url("/suscripcion/cancelar") ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline-secondary w-100">Bajar a Trial ($0)</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-light w-100 text-muted disabled">Solo un admin puede cambiar el plan</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plan 2: $147 -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100 <?= ($planActual === "pro") ? "border border-2 border-success" : "" ?>">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-dark mb-0">Industrial Pro</h5>
                            <?php if ($planActual === "pro"): ?>
                                <span class="badge bg-success">PLAN ACTUAL</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success">POPULAR</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-secondary small mb-3">Para plantas continuas y técnicos especializados.</p>
                        <div class="mb-3">
                            <span class="display-6 fw-bold text-dark">$147</span>
                            <span class="text-muted small">/ mes por planta</span>
                        </div>
                        <hr>
                        <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary">
                            <li><i class="bi bi-check-lg text-success me-2"></i> <strong>Activos Ilimitados</strong></li>
                            <li><i class="bi bi-check-lg text-success me-2"></i> <strong>Asistente ORION RAG Ilimitado</strong></li>
                            <li><i class="bi bi-check-lg text-success me-2"></i> Servidor MCP & Tokens API</li>
                            <li><i class="bi bi-check-lg text-success me-2"></i> Mapa de Conocimiento 3D</li>
                            <li><i class="bi bi-check-lg text-success me-2"></i> Runbooks Autoevolutivos</li>
                        </ul>
                    </div>
                    <div class="mt-4">
                        <?php if ($planActual === "pro"): ?>
                            <button class="btn btn-success w-100 disabled">Plan Activo</button>
                        <?php else: ?>
                            <a href="<?= base_url("/suscripcion/checkout?plan=pro") ?>" class="btn btn-outline-success w-100 fw-bold">
                                <i class="bi bi-lightning-charge-fill"></i> Elegir Pro ($147/mes)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plan 3: $997 (El más avanzado) -->
        <div class="col-lg-4">
            <div class="card shadow border-0 h-100 <?= ($planActual === "enterprise") ? "border border-2 border-primary" : "border border-primary" ?>" style="background: linear-gradient(180deg, #FFFFFF 0%, #F0F9FF 100%);">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-primary mb-0"><i class="bi bi-stars"></i> Enterprise Global</h5>
                            <?php if ($planActual === "enterprise"): ?>
                                <span class="badge bg-primary">PLAN ACTUAL</span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary">MÁS AVANZADO</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-secondary small mb-3">La suite completa para corporativos multi-planta.</p>
                        <div class="mb-3">
                            <span class="display-6 fw-bold text-primary">$997</span>
                            <span class="text-muted small">/ mes corporativo</span>
                        </div>
                        <hr>
                        <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary">
                            <li><i class="bi bi-check-lg text-primary me-2"></i> <strong>Multi-Planta & Sedes Globales</strong></li>
                            <li><i class="bi bi-check-lg text-primary me-2"></i> <strong>Despliegue On-Premise / Híbrido</strong></li>
                            <li><i class="bi bi-check-lg text-primary me-2"></i> <strong>Conectores Directos OPC UA / SCADA</strong></li>
                            <li><i class="bi bi-check-lg text-primary me-2"></i> RAG Personalizado con manuales internos</li>
                            <li><i class="bi bi-check-lg text-primary me-2"></i> SLA 99.99% & Soporte 24/7 en sitio</li>
                        </ul>
                    </div>
                    <div class="mt-4">
                        <?php if ($planActual === "enterprise"): ?>
                            <button class="btn btn-primary w-100 disabled fw-bold">
                                <i class="bi bi-check-circle-fill"></i> Plan Más Avanzado Activo
                            </button>
                        <?php elseif ($esAdmin): ?>
                            <form method="post" action="<?= base_url("/suscripcion/activar-enterprise") ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                                    <i class="bi bi-rocket-takeoff-fill"></i> Activar Enterprise ($997/mes)
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-light w-100 text-muted disabled">Solo un admin puede cambiar el plan</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla: Historial de Transacciones & Reembolsos -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">Historial de Pagos & Solicitud de Reembolsos</h5>
            <small class="text-secondary">Transacciones procesadas y comprobadas vía Stripe</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID Transacción</th>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Moneda</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones de Reembolso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagos)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">
                                <i class="bi bi-receipt fs-3 d-block mb-1 text-muted"></i>
                                No hay transacciones registradas aún para esta organización.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagos as $p): ?>
                            <tr>
                                <td>
                                    <code class="text-dark small"><?= e(substr($p["stripe_session_id"] ?? $p["stripe_charge_id"] ?? "TX-".$p["id"], 0, 24)) ?>...</code>
                                </td>
                                <td><small><?= e($p["created_at"]) ?></small></td>
                                <td><strong>$<?= number_format((float)$p["monto"], 2) ?></strong></td>
                                <td><span class="badge bg-light text-dark text-uppercase"><?= e($p["moneda"]) ?></span></td>
                                <td>
                                    <?php if ($p["estado"] === "paid"): ?>
                                        <span class="badge bg-success">Completado</span>
                                    <?php elseif ($p["estado"] === "refunded"): ?>
                                        <span class="badge bg-info text-dark">Reembolsado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><?= e($p["estado"]) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($p["estado"] === "paid" && $esAdmin): ?>
                                        <form method="post" action="<?= base_url("/suscripcion/reembolso") ?>" class="d-inline" onsubmit="return confirm('¿Confirmas emitir el reembolso de este pago en Stripe?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="pago_id" value="<?= (int)$p["id"] ?>">
                                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                                <i class="bi bi-arrow-counterclockwise"></i> Reembolsar ($<?= number_format((float)$p["monto"], 2) ?>)
                                            </button>
                                        </form>
                                    <?php elseif ($p["estado"] === "refunded"): ?>
                                        <small class="text-success"><i class="bi bi-check-circle-fill"></i> Reembolso ejecutado</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . "/../../layouts/footer.php"; ?>
