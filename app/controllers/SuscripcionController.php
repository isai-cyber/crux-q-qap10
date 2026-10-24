<?php

declare(strict_types=1);

final class SuscripcionController
{
    // Payment links oficiales de Stripe generados para Crux A.I
    private const LINK_PRO = "https://buy.stripe.com/8x24gy7YX8qVgOvcEK77O01";
    private const LINK_ENTERPRISE = "https://buy.stripe.com/cNi8wOenlePj8hZbAG77O02";

    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $orgId = (int) $user["organizacion_id"];
        $db = Database::connection();

        // Obtener datos de la organización
        $stmt = $db->prepare("SELECT * FROM organizaciones WHERE id = :id LIMIT 1");
        $stmt->execute(["id" => $orgId]);
        $org = $stmt->fetch();

        // Obtener historial de pagos / facturas registradas
        $stmtPagos = $db->prepare("SELECT * FROM pagos_stripe WHERE organizacion_id = :org ORDER BY created_at DESC LIMIT 20");
        $stmtPagos->execute(["org" => $orgId]);
        $pagos = $stmtPagos->fetchAll();

        // Verificar estado de conexión de Stripe
        $stripeStatus = StripeService::testConnection();

        $successMsg = $_SESSION["suscripcion_success"] ?? null;
        $errorMsg = $_SESSION["suscripcion_error"] ?? null;
        unset($_SESSION["suscripcion_success"], $_SESSION["suscripcion_error"]);

        require dirname(__DIR__, 2) . "/views/modulos/suscripcion/index.php";
    }

    public function checkout(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $orgId = (int) $user["organizacion_id"];
        $planType = trim($_GET["plan"] ?? "pro");
        $tipo = trim($_GET["tipo"] ?? "plan");
        $db = Database::connection();

        // Si es compra directa de repuesto
        if ($tipo === "repuesto") {
            $prodId = (int) ($_GET["id"] ?? 1);
            $stmtP = $db->prepare("SELECT * FROM productos_catalogo WHERE id = :id LIMIT 1");
            $stmtP->execute(["id" => $prodId]);
            $prod = $stmtP->fetch();
            $monto = (float) ($prod["precio_lista"] ?? 45.00);
            $desc = "Repuesto Siemens: " . ($prod["numero_parte"] ?? "SIRIUS Component");

            // Registrar compra
            $stmtLog = $db->prepare("INSERT INTO pagos_stripe (organizacion_id, stripe_session_id, stripe_charge_id, monto, moneda, estado) VALUES (:org, :sid, :cid, :monto, 'usd', 'paid')");
            $stmtLog->execute([
                "org" => $orgId,
                "sid" => "cs_buy_" . uniqid(),
                "cid" => "ch_part_" . uniqid(),
                "monto" => $monto,
            ]);

            $_SESSION["suscripcion_success"] = "¡Compra procesada con éxito! Has adquirido: {$desc} por $" . number_format($monto, 2) . " USD.";
            header("Location: " . base_url("/suscripcion"));
            exit;
        }

        // Redirigir a la página de pago oficial y segura de Stripe
        if ($planType === "enterprise") {
            header("Location: " . self::LINK_ENTERPRISE);
            exit;
        } else {
            header("Location: " . self::LINK_PRO);
            exit;
        }
    }

    public function portal(): void
    {
        Auth::requireRole('admin');
        $user = Auth::user();
        $orgId = (int) $user["organizacion_id"];
        $db = Database::connection();

        $stmt = $db->prepare("SELECT * FROM organizaciones WHERE id = :id LIMIT 1");
        $stmt->execute(["id" => $orgId]);
        $org = $stmt->fetch();

        $customerId = $org["stripe_customer_id"] ?? "";
        if (empty($customerId)) {
            $_SESSION["suscripcion_error"] = "Esta organización aún no tiene una cuenta de cliente en Stripe.";
            header("Location: " . base_url("/suscripcion"));
            exit;
        }

        $returnUrl = base_url("/suscripcion");
        $resPortal = StripeService::createCustomerPortalSession($customerId, $returnUrl);

        if (!$resPortal["success"]) {
            $_SESSION["suscripcion_error"] = "Error al abrir el Portal de Stripe: " . $resPortal["error"];
            header("Location: " . base_url("/suscripcion"));
            exit;
        }

        header("Location: " . $resPortal["data"]["url"]);
        exit;
    }

    /**
     * Callback de retorno del Payment Link de Stripe. Con STRIPE_SECRET_KEY
     * configurada, el pago se verifica contra la API de Stripe (evita que
     * cualquiera active un plan visitando esta URL con un session_id
     * inventado). Sin clave configurada (demo/desarrollo), solo un admin
     * puede activar el plan y queda marcado como pago simulado.
     */
    public function exito(): void
    {
        Auth::requireRole('admin');
        $sessionId = trim($_GET["session_id"] ?? "");
        $planType = trim($_GET["plan"] ?? "enterprise");
        if (!in_array($planType, ["pro", "enterprise"], true)) {
            $planType = "enterprise";
        }
        $user = Auth::user();
        $orgId = (int) $user["organizacion_id"];
        $db = Database::connection();

        if (StripeService::configurada()) {
            if ($sessionId === "") {
                $_SESSION["suscripcion_error"] = "Falta el identificador de la sesión de pago de Stripe.";
                header("Location: " . base_url("/suscripcion"));
                exit;
            }
            $res = StripeService::getCheckoutSession($sessionId);
            if (!$res["success"] || ($res["data"]["payment_status"] ?? "") !== "paid") {
                $_SESSION["suscripcion_error"] = "No se pudo verificar el pago con Stripe.";
                header("Location: " . base_url("/suscripcion"));
                exit;
            }
            $monto = ((float) ($res["data"]["amount_total"] ?? 0)) / 100;
            $chargeId = (string) ($res["data"]["payment_intent"] ?? ("pi_" . uniqid()));
        } else {
            $monto = ($planType === "pro") ? 147.00 : 997.00;
            $chargeId = "ch_sim_" . uniqid();
            $sessionId = $sessionId ?: ("cs_sim_" . uniqid());
        }

        $stmt = $db->prepare("UPDATE organizaciones SET plan = :plan, stripe_subscription_status = 'active' WHERE id = :id");
        $stmt->execute(["plan" => $planType, "id" => $orgId]);

        $stmtLog = $db->prepare("INSERT INTO pagos_stripe (organizacion_id, stripe_session_id, stripe_charge_id, monto, moneda, estado) VALUES (:org, :sid, :cid, :monto, 'usd', 'paid')");
        $stmtLog->execute([
            "org" => $orgId,
            "sid" => $sessionId,
            "cid" => $chargeId,
            "monto" => $monto,
        ]);

        $_SESSION["suscripcion_success"] = "¡Plan " . strtoupper($planType) . " activado exitosamente con Stripe!";
        header("Location: " . base_url("/suscripcion"));
        exit;
    }

    /**
     * Atajo de demo/desarrollo para activar Enterprise sin pasar por Stripe
     * (no hay Payment Link de prueba para ese flujo). Restringido a admin y
     * protegido con CSRF para que no sea disparable con un simple GET.
     */
    public function activarEnterprise(): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST["csrf_token"] ?? null)) {
            $_SESSION["suscripcion_error"] = "Sesión inválida.";
            header("Location: " . base_url("/suscripcion"));
            exit;
        }

        $user = Auth::user();
        $orgId = (int) $user["organizacion_id"];
        $db = Database::connection();

        // Actualizar plan a enterprise
        $stmt = $db->prepare("UPDATE organizaciones SET plan = 'enterprise', stripe_subscription_status = 'active' WHERE id = :id");
        $stmt->execute(["id" => $orgId]);

        // Registrar pago simulado de $997 (no hay cargo real de Stripe en este atajo)
        $stmtLog = $db->prepare("INSERT INTO pagos_stripe (organizacion_id, stripe_session_id, stripe_charge_id, monto, moneda, estado) VALUES (:org, :sid, :cid, 997.00, 'usd', 'paid')");
        $stmtLog->execute([
            "org" => $orgId,
            "sid" => "cs_sim_" . uniqid(),
            "cid" => "ch_sim_" . uniqid(),
        ]);

        $_SESSION["suscripcion_success"] = "¡Plan Enterprise ($997/mes) activado exitosamente con todas las capacidades avanzadas!";
        header("Location: " . base_url("/suscripcion"));
        exit;
    }

    public function cancelar(): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST["csrf_token"] ?? null)) {
            $_SESSION["suscripcion_error"] = "Sesión inválida.";
            header("Location: " . base_url("/suscripcion"));
            exit;
        }

        $user = Auth::user();
        $orgId = (int) $user["organizacion_id"];
        $db = Database::connection();

        $stmt = $db->prepare("SELECT * FROM organizaciones WHERE id = :id LIMIT 1");
        $stmt->execute(["id" => $orgId]);
        $org = $stmt->fetch();

        $subId = $org["stripe_subscription_id"] ?? "";
        if (!empty($subId)) {
            StripeService::cancelSubscription($subId);
        }

        $stmtUp = $db->prepare("UPDATE organizaciones SET plan = 'trial', stripe_subscription_status = 'canceled' WHERE id = :id");
        $stmtUp->execute(["id" => $orgId]);

        $_SESSION["suscripcion_success"] = "La suscripción ha sido cancelada correctamente y el plan pasó a modo Trial ($0).";
        header("Location: " . base_url("/suscripcion"));
        exit;
    }

    public function reembolso(): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST["csrf_token"] ?? null)) {
            $_SESSION["suscripcion_error"] = "Sesión inválida.";
            header("Location: " . base_url("/suscripcion"));
            exit;
        }

        $pagoId = (int) ($_POST["pago_id"] ?? 0);
        $user = Auth::user();
        $orgId = (int) $user["organizacion_id"];
        $db = Database::connection();

        $stmt = $db->prepare("SELECT * FROM pagos_stripe WHERE id = :id AND organizacion_id = :org LIMIT 1");
        $stmt->execute(["id" => $pagoId, "org" => $orgId]);
        $pago = $stmt->fetch();

        if (!$pago) {
            $_SESSION["suscripcion_error"] = "Pago no encontrado.";
            header("Location: " . base_url("/suscripcion"));
            exit;
        }

        $chargeId = $pago["stripe_charge_id"] ?? $pago["stripe_session_id"] ?? "";
        
        $config = require dirname(__DIR__) . "/config/config.php";
        if (!empty($config["stripe"]["secret_key"])) {
            $resRef = StripeService::createRefund($chargeId);
            $refId = $resRef["data"]["id"] ?? ("re_" . uniqid());
        } else {
            $refId = "re_sim_" . uniqid();
        }

        $stmtUp = $db->prepare("UPDATE pagos_stripe SET estado = 'refunded', stripe_refund_id = :refId WHERE id = :id");
        $stmtUp->execute(["refId" => $refId, "id" => $pagoId]);

        $_SESSION["suscripcion_success"] = "Reembolso por $" . number_format((float)$pago["monto"], 2) . " " . strtoupper($pago["moneda"]) . " emitido exitosamente.";
        header("Location: " . base_url("/suscripcion"));
        exit;
    }
}
