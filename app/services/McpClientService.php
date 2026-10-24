<?php

declare(strict_types=1);

/**
 * Cliente MCP (JSON-RPC 2.0 sobre HTTP) para que ORION consuma servidores
 * MCP externos al nicho (scanners de red, ERPs de repuestos, otros CMMS,
 * etc.), dados de alta desde el chat — ver ChatController::manejarGestionMcp().
 * Complementa a McpController, que es el servidor (expone las herramientas
 * de esta app hacia afuera).
 */
final class McpClientService
{
    private const TIMEOUT = 15;

    /**
     * Descubre las herramientas de un servidor MCP externo (tools/list).
     * Devuelve ['success' => bool, 'herramientas' => array, 'error' => ?string]
     */
    public static function descubrirHerramientas(string $url, ?string $token): array
    {
        $res = self::rpc($url, $token, 'tools/list', []);
        if (!$res['success']) {
            return $res;
        }
        $herramientas = $res['resultado']['tools'] ?? null;
        if (!is_array($herramientas)) {
            return ['success' => false, 'herramientas' => [], 'error' => 'El servidor no devolvió una lista de herramientas válida.'];
        }
        return ['success' => true, 'herramientas' => $herramientas, 'error' => null];
    }

    /**
     * Invoca una herramienta remota (tools/call). Devuelve el texto de
     * respuesta ya extraído del content[] de MCP, listo para mostrar en chat.
     */
    public static function llamarHerramienta(string $url, ?string $token, string $herramienta, array $argumentos): array
    {
        $res = self::rpc($url, $token, 'tools/call', ['name' => $herramienta, 'arguments' => $argumentos]);
        if (!$res['success']) {
            return $res;
        }

        $contenido = $res['resultado']['content'] ?? [];
        $texto = '';
        foreach ($contenido as $bloque) {
            if (($bloque['type'] ?? '') === 'text') {
                $texto .= $bloque['text'];
            }
        }

        return ['success' => true, 'texto' => $texto !== '' ? $texto : json_encode($res['resultado'], JSON_UNESCAPED_UNICODE), 'error' => null];
    }

    private static function rpc(string $url, ?string $token, string $method, array $params): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'resultado' => null, 'error' => 'La URL del servidor MCP no es válida.'];
        }

        $headers = ['Content-Type: application/json'];
        if (!empty($token)) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params,
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $respuesta = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            return ['success' => false, 'resultado' => null, 'error' => 'No se pudo conectar: ' . $curlError];
        }

        $json = json_decode((string) $respuesta, true);
        if ($httpCode >= 400 || !is_array($json)) {
            return ['success' => false, 'resultado' => null, 'error' => 'El servidor respondió HTTP ' . $httpCode . '.'];
        }
        if (isset($json['error'])) {
            return ['success' => false, 'resultado' => null, 'error' => $json['error']['message'] ?? 'Error MCP desconocido.'];
        }

        return ['success' => true, 'resultado' => $json['result'] ?? [], 'error' => null];
    }
}
