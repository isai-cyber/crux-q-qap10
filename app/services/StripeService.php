<?php

declare(strict_types=1);

final class StripeService
{
    private static function getApiKey(): string
    {
        $config = require dirname(__DIR__) . "/config/config.php";
        return (string) ($config["stripe"]["secret_key"] ?? "");
    }

    private static function request(string $method, string $endpoint, array $data = []): array
    {
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return [
                "success" => false,
                "error" => "STRIPE_SECRET_KEY no configurada en el archivo .env",
            ];
        }

        $url = "https://api.stripe.com/v1/" . ltrim($endpoint, "/");
        $ch = curl_init();

        $headers = [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/x-www-form-urlencoded",
        ];

        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        } elseif ($method === "GET" && !empty($data)) {
            $url .= "?" . http_build_query($data);
        } elseif ($method === "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            return [
                "success" => false,
                "error" => "Error de conexión cURL: " . $curlError,
            ];
        }

        $json = json_decode((string) $response, true);
        if ($httpCode >= 400 || isset($json["error"])) {
            return [
                "success" => false,
                "error" => $json["error"]["message"] ?? ("Error Stripe HTTP " . $httpCode),
                "details" => $json,
            ];
        }

        return [
            "success" => true,
            "data" => $json,
        ];
    }

    public static function createCustomer(string $email, string $name, array $metadata = []): array
    {
        return self::request("POST", "customers", [
            "email" => $email,
            "name" => $name,
            "metadata" => $metadata,
        ]);
    }

    public static function createCheckoutSession(string $customerId, string $priceId, string $successUrl, string $cancelUrl, array $metadata = []): array
    {
        $params = [
            "customer" => $customerId,
            "mode" => "subscription",
            "payment_method_types" => ["card"],
            "line_items" => [
                [
                    "price" => $priceId,
                    "quantity" => 1,
                ]
            ],
            "success_url" => $successUrl,
            "cancel_url" => $cancelUrl,
            "metadata" => $metadata,
        ];

        return self::request("POST", "checkout/sessions", $params);
    }

    public static function createCustomerPortalSession(string $customerId, string $returnUrl): array
    {
        return self::request("POST", "billing_portal/sessions", [
            "customer" => $customerId,
            "return_url" => $returnUrl,
        ]);
    }

    public static function getSubscription(string $subscriptionId): array
    {
        return self::request("GET", "subscriptions/" . $subscriptionId);
    }

    public static function getCheckoutSession(string $sessionId): array
    {
        return self::request("GET", "checkout/sessions/" . $sessionId);
    }

    public static function configurada(): bool
    {
        return self::getApiKey() !== "";
    }

    public static function cancelSubscription(string $subscriptionId): array
    {
        return self::request("DELETE", "subscriptions/" . $subscriptionId);
    }

    public static function listCustomerInvoices(string $customerId, int $limit = 10): array
    {
        return self::request("GET", "invoices", [
            "customer" => $customerId,
            "limit" => $limit,
        ]);
    }

    public static function listCharges(string $customerId = "", int $limit = 10): array
    {
        $params = ["limit" => $limit];
        if (!empty($customerId)) {
            $params["customer"] = $customerId;
        }
        return self::request("GET", "charges", $params);
    }

    public static function createRefund(string $chargeOrPaymentIntentId, ?int $amountCents = null, string $reason = "requested_by_customer"): array
    {
        $params = [
            "reason" => $reason,
        ];
        if (str_starts_with($chargeOrPaymentIntentId, "pi_")) {
            $params["payment_intent"] = $chargeOrPaymentIntentId;
        } else {
            $params["charge"] = $chargeOrPaymentIntentId;
        }
        if ($amountCents !== null && $amountCents > 0) {
            $params["amount"] = $amountCents;
        }

        return self::request("POST", "refunds", $params);
    }

    public static function testConnection(): array
    {
        return self::request("GET", "balance");
    }
}
