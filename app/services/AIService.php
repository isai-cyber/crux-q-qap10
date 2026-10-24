<?php

declare(strict_types=1);

/**
 * Adaptador HTTP a un proveedor de IA generativa configurado vía .env.
 * Aísla al resto de la app del proveedor concreto (OpenAI, Anthropic, etc.).
 *
 * Autoevolución: triageTicket() no usa un prompt fijo — cada llamada incluye
 * como ejemplos few-shot los últimos tickets que un humano clasificó/corrigió
 * (categoria_final/prioridad_final). Cuantos más tickets se cierren y
 * corrijan, más ejemplos reales tiene el prompt, así que la clasificación se
 * alinea sola con el criterio humano sin reentrenar ningún modelo.
 */
final class AIService
{
    /**
     * Persona compartida por todos los prompts: ancla al modelo al nicho de
     * automatización industrial/mantenimiento (en vez de un asistente
     * genérico), coherente con el dominio de los documentos fuente del
     * proyecto (catálogo Siemens SIRIUS, manuales SINUMERIK/STEP7 y SIMATIC
     * Automation Tool). Ver ChatController::NOMBRE_ASISTENTE para el nombre
     * de cara al usuario (ORION) en el chat.
     */
    private const PERSONA = 'Eres ORION, el asistente técnico de un CMMS (sistema de gestión de mantenimiento) '
        . 'especializado en automatización industrial: PLCs, controladores SIMATIC/SINUMERIK, arrancadores y '
        . 'contactores SIRIUS, y mantenimiento de plantas. Responde con precisión técnica, en español, breve y '
        . 'accionable para un técnico de mantenimiento o un supervisor de planta.';

    private array $config;
    private ?int $organizacionId;

    /** Consumo de la última llamada (tokens prompt/completion + costo). */
    private ?array $ultimoUso = null;

    /**
     * $organizacionId aísla los ejemplos few-shot y el registro de auditoría
     * (logs_ia) por organización. Si se omite, se toma de la sesión web
     * actual; en contexto de API (sin sesión) debe pasarse explícitamente
     * con el organizacion_id del token usado.
     *
     * Cada organización puede traer su propia clave de IA (configurada desde
     * /admin/organizacion, onboarding); si no configuró nada, se usa el
     * proveedor global del .env como respaldo.
     */
    public function __construct(?int $organizacionId = null)
    {
        $this->config = (require __DIR__ . '/../config/config.php')['ai'];
        $this->organizacionId = $organizacionId ?? (Auth::user()['organizacion_id'] ?? null);

        if ($this->organizacionId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT ai_provider, ai_api_key, ai_model FROM organizaciones WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $this->organizacionId]);
            $orgAi = $stmt->fetch();
            if ($orgAi && !empty($orgAi['ai_api_key'])) {
                $this->config['provider'] = $orgAi['ai_provider'] ?: $this->config['provider'];
                $this->config['api_key'] = $orgAi['ai_api_key'];
                $this->config['model'] = $orgAi['ai_model'] ?: $this->config['model'];
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->config['api_key'] !== '' && $this->config['api_key'] !== null;
    }

    /**
     * Clasifica un ticket a partir de su descripción libre.
     * Devuelve ['categoria' => string, 'prioridad' => 'baja'|'media'|'alta'|'critica', 'razon' => string]
     * Si no hay proveedor configurado, aplica una heurística simple como respaldo.
     */
    public function triageTicket(string $descripcion): array
    {
        if (!$this->isConfigured()) {
            return $this->triageFallback($descripcion);
        }

        $ejemplos = $this->obtenerEjemplosValidados();
        $bloqueEjemplos = $ejemplos === ''
            ? ''
            : "Ejemplos reales ya corregidos por un supervisor (usa el mismo criterio):\n{$ejemplos}\n\n";

        $prompt = self::PERSONA . "\n\nClasifica el siguiente reporte de falla de un equipo industrial. "
            . "Responde SOLO JSON con las claves categoria, prioridad (baja|media|alta|critica) y razon.\n\n"
            . $bloqueEjemplos
            . "Reporte: {$descripcion}";

        $start = microtime(true);
        $respuesta = $this->callChatCompletion($prompt, true);
        $latencia = (int) ((microtime(true) - $start) * 1000);

        $data = json_decode($respuesta ?? '', true);
        if (!is_array($data) || !isset($data['categoria'], $data['prioridad'])) {
            $data = $this->triageFallback($descripcion);
        }

        $this->logCall('triage_ticket', $descripcion, $respuesta ?? '', $latencia);

        return $data;
    }

    /**
     * Genera un diagrama técnico (eléctrico/de control) en SVG a partir de una
     * descripción y, opcionalmente, los productos del catálogo involucrados.
     */
    public function generateDiagram(string $descripcion, array $productos = []): string
    {
        if (!$this->isConfigured()) {
            return $this->diagramaFallback($descripcion);
        }

        $listaProductos = $productos
            ? "Componentes del catálogo a incluir:\n" . implode("\n", array_map(
                fn ($p) => "- {$p['numero_parte']}: {$p['descripcion']}",
                $productos
            )) . "\n\n"
            : '';

        $prompt = "Genera un diagrama esquemático simple en SVG (solo el código <svg>...</svg>, "
            . "sin explicación, sin markdown, viewBox 0 0 600 400, formas básicas y texto legible) "
            . "para el siguiente requerimiento de control/automatización industrial.\n\n"
            . $listaProductos
            . "Requerimiento: {$descripcion}";

        $start = microtime(true);
        $respuesta = $this->callChatCompletion($prompt, false);
        $latencia = (int) ((microtime(true) - $start) * 1000);
        $this->logCall('generate_diagram', $descripcion, $respuesta ?? '', $latencia);

        $svg = $this->extraerSvg($respuesta ?? '');
        return $svg ?? $this->diagramaFallback($descripcion);
    }

    /**
     * Genera un documento técnico estructurado (Markdown): alcance, materiales
     * (BOM), procedimiento paso a paso y notas de seguridad.
     */
    public function generateTechDoc(string $descripcion, array $contexto = []): string
    {
        if (!$this->isConfigured()) {
            return $this->documentoFallback($descripcion);
        }

        $bloqueContexto = $contexto
            ? "Contexto adicional:\n" . implode("\n", array_map(
                fn ($k, $v) => "- {$k}: {$v}",
                array_keys($contexto),
                $contexto
            )) . "\n\n"
            : '';

        $prompt = "Redacta un documento técnico en Markdown para mantenimiento industrial, con las "
            . "secciones: ## Alcance, ## Materiales (BOM en tabla), ## Procedimiento (pasos numerados) "
            . "y ## Notas de seguridad. Sé conciso y técnico.\n\n"
            . $bloqueContexto
            . "Solicitud: {$descripcion}";

        $start = microtime(true);
        $respuesta = $this->callChatCompletion($prompt, false);
        $latencia = (int) ((microtime(true) - $start) * 1000);
        $this->logCall('generate_tech_doc', $descripcion, $respuesta ?? '', $latencia);

        return $respuesta !== null && trim($respuesta) !== '' ? $respuesta : $this->documentoFallback($descripcion);
    }

    /**
     * Extrae nombre/email/rol/password de un mensaje de chat en lenguaje
     * libre (usado por ChatController para crear usuarios sin salir del
     * chat). Devuelve null si no hay IA configurada o si no pudo extraer
     * los campos con confianza; el llamador tiene un fallback por regex.
     */
    public function extraerDatosUsuario(string $mensaje): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $prompt = 'Extrae de este mensaje los datos para crear un usuario del sistema. '
            . 'Responde SOLO JSON con las claves nombre, email, rol (admin|supervisor|tecnico) y password '
            . '(si no se indica contraseña, inventa una aleatoria de 10 caracteres). '
            . "Si falta el nombre o el email, responde {\"error\": true}.\n\nMensaje: {$mensaje}";

        $respuesta = $this->callChatCompletion($prompt, true);
        $this->logCall('extraer_datos_usuario', $mensaje, $respuesta ?? '', 0);

        $data = json_decode($respuesta ?? '', true);
        if (!is_array($data) || !empty($data['error']) || empty($data['nombre']) || empty($data['email'])) {
            return null;
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $data['rol'] = in_array($data['rol'] ?? '', ['admin', 'supervisor', 'tecnico'], true) ? $data['rol'] : 'tecnico';
        $data['password'] = $data['password'] ?? bin2hex(random_bytes(5));
        return $data;
    }

    /**
     * Extrae los argumentos para llamar una herramienta MCP externa a partir
     * de su inputSchema (JSON Schema) y el mensaje libre del usuario. Usado
     * por ChatController para invocar herramientas de conexiones MCP dadas
     * de alta por chat sin que el usuario tenga que escribir JSON a mano.
     * Sin IA configurada, cae a un llenado ingenuo de la única propiedad
     * requerida (si hay exactamente una).
     */
    public function extraerArgumentosHerramienta(string $mensaje, array $inputSchema): ?array
    {
        $propiedades = $inputSchema['properties'] ?? [];
        $requeridas = $inputSchema['required'] ?? [];

        if ($this->isConfigured() && $propiedades) {
            $prompt = 'Extrae de este mensaje los argumentos para llamar una herramienta, según este JSON Schema '
                . "de propiedades esperadas:\n" . json_encode($propiedades, JSON_UNESCAPED_UNICODE)
                . "\nCampos requeridos: " . json_encode($requeridas) . "\n"
                . "Responde SOLO un objeto JSON con esos argumentos (usa solo las claves del schema). "
                . "Si no puedes inferir un campo requerido, responde {\"error\": true}.\n\nMensaje: {$mensaje}";

            $respuesta = $this->callChatCompletion($prompt, true);
            $this->logCall('extraer_argumentos_herramienta', $mensaje, $respuesta ?? '', 0);

            $data = json_decode($respuesta ?? '', true);
            if (is_array($data) && empty($data['error'])) {
                return $data;
            }
        }

        if (count($requeridas) === 1 && isset($propiedades[$requeridas[0]])) {
            return [$requeridas[0] => $mensaje];
        }

        return null;
    }

    /**
     * RAG sobre la base de conocimiento (manuales BE2/SAT, ver
     * base_conocimiento_chunks — GLOBAL, compartida entre organizaciones).
     * Recuperación: coincidencia de palabras clave (funciona sin IA
     * configurada y sin generar embeddings). Si hay IA configurada, además
     * sintetiza una respuesta en lenguaje natural citando el documento
     * fuente; si no, devuelve los fragmentos encontrados tal cual.
     *
     * Nota de alcance: la ingesta completa de los manuales (BE2: 1451 pág.,
     * SAT: 187 pág.) no se ha hecho — requiere generar embeddings reales con
     * AI_API_KEY y es una tarea aparte. Esta base está sembrada con una
     * muestra real de ambos documentos para que el flujo sea comprobable de
     * punta a punta; ampliar la cobertura es cuestión de ingestar más chunks,
     * no de cambiar código.
     */
    public function responderConManuales(string $pregunta, int $limite = 4): array
    {
        $palabras = array_values(array_filter(
            preg_split('/\s+/', mb_strtolower($pregunta)),
            fn ($w) => mb_strlen($w) >= 4
        ));

        $fragmentos = [];
        if ($palabras) {
            $condiciones = [];
            $params = [];
            foreach ($palabras as $i => $palabra) {
                $condiciones[] = "texto LIKE :p{$i}";
                $params["p{$i}"] = "%{$palabra}%";
            }
            $sql = 'SELECT documento_origen, capitulo, texto FROM base_conocimiento_chunks
                    WHERE ' . implode(' OR ', $condiciones) . ' LIMIT :limite';
            $stmt = Database::connection()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $fragmentos = $stmt->fetchAll();
        }

        if (!$fragmentos) {
            return [
                'respuesta' => 'No encontré información sobre eso en los manuales indexados todavía. '
                    . 'La base de conocimiento tiene solo una muestra de BE2 (SINUMERIK) y SAT (SIMATIC Automation Tool); '
                    . 'para cubrir más temas hace falta ingestar más contenido de esos manuales.',
                'fuentes' => [],
            ];
        }

        if (!$this->isConfigured()) {
            $texto = implode("\n\n", array_map(
                fn ($f) => "[{$f['documento_origen']} — {$f['capitulo']}]\n{$f['texto']}",
                $fragmentos
            ));
            return ['respuesta' => "Encontré esto en los manuales:\n\n{$texto}", 'fuentes' => $fragmentos];
        }

        $contexto = implode("\n\n", array_map(
            fn ($f) => "Fuente: {$f['documento_origen']} — {$f['capitulo']}\n{$f['texto']}",
            $fragmentos
        ));
        $prompt = self::PERSONA . "\n\nResponde la siguiente pregunta usando SOLO la información de estos fragmentos "
            . "de manuales técnicos, citando el documento fuente. Si los fragmentos no alcanzan para responder, dilo.\n\n"
            . "Fragmentos:\n{$contexto}\n\nPregunta: {$pregunta}";

        $start = microtime(true);
        $respuesta = $this->callChatCompletion($prompt, false);
        $latencia = (int) ((microtime(true) - $start) * 1000);
        $this->logCall('rag_manuales', $pregunta, $respuesta ?? '', $latencia);

        return ['respuesta' => $respuesta ?? 'No pude generar una respuesta.', 'fuentes' => $fragmentos];
    }

    /**
     * Últimos tickets donde un supervisor corrigió/confirmó la clasificación
     * de la IA. Es el "dataset" vivo que hace evolucionar el triage.
     */
    private function obtenerEjemplosValidados(int $limite = 5): string
    {
        if ($this->organizacionId === null) {
            return '';
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT descripcion, categoria_final, prioridad_final FROM tickets
                 WHERE organizacion_id = :org AND categoria_final IS NOT NULL AND prioridad_final IS NOT NULL
                 ORDER BY updated_at DESC LIMIT :limite'
            );
            $stmt->bindValue(':org', $this->organizacionId, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $filas = $stmt->fetchAll();
        } catch (Throwable $e) {
            return '';
        }

        if (!$filas) {
            return '';
        }

        return implode("\n", array_map(
            fn ($f) => "- Reporte: \"{$f['descripcion']}\" → categoria={$f['categoria_final']}, prioridad={$f['prioridad_final']}",
            $filas
        ));
    }

    private function extraerSvg(string $texto): ?string
    {
        if (preg_match('/<svg[\s\S]*<\/svg>/i', $texto, $m)) {
            return $m[0];
        }
        return null;
    }

    private function diagramaFallback(string $descripcion): string
    {
        $texto = htmlspecialchars($descripcion, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return <<<SVG
        <svg viewBox="0 0 600 200" xmlns="http://www.w3.org/2000/svg">
            <rect width="600" height="200" fill="#f8f9fa" stroke="#dee2e6"/>
            <text x="300" y="90" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#6c757d">
                Diagrama no disponible: configure AI_API_KEY en .env para generarlo.
            </text>
            <text x="300" y="115" text-anchor="middle" font-family="sans-serif" font-size="12" fill="#adb5bd">
                Solicitud: {$texto}
            </text>
        </svg>
        SVG;
    }

    private function documentoFallback(string $descripcion): string
    {
        return "## Documento no disponible\n\n"
            . "Configure `AI_API_KEY` en `.env` para generar documentos técnicos con IA.\n\n"
            . "**Solicitud registrada:** {$descripcion}";
    }

    private function triageFallback(string $descripcion): array
    {
        $texto = mb_strtolower($descripcion);
        $prioridad = 'media';
        if (preg_match('/(no arranca|parado|detenido|urgente|fuego|humo)/', $texto)) {
            $prioridad = 'critica';
        } elseif (preg_match('/(intermitente|a veces|ruido)/', $texto)) {
            $prioridad = 'alta';
        }
        return [
            'categoria' => 'sin_clasificar',
            'prioridad' => $prioridad,
            'razon' => 'Clasificación heurística de respaldo (proveedor de IA no configurado).',
        ];
    }

    private function callChatCompletion(string $prompt, bool $jsonMode): ?string
    {
        $url = match ($this->config['provider']) {
            'anthropic' => 'https://api.anthropic.com/v1/messages',
            'deepseek' => 'https://api.deepseek.com/chat/completions',
            default => 'https://api.openai.com/v1/chat/completions',
        };

        $headers = ['Content-Type: application/json'];
        if ($this->config['provider'] === 'anthropic') {
            $headers[] = 'x-api-key: ' . $this->config['api_key'];
            $headers[] = 'anthropic-version: 2023-06-01';
            $payload = [
                'model' => $this->config['model'],
                'max_tokens' => 1500,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ];
        } else {
            // OpenAI y DeepSeek comparten el mismo formato de API (DeepSeek es
            // compatible con el SDK/endpoint de OpenAI, solo cambia la URL base
            // y el nombre del modelo: deepseek-chat / deepseek-reasoner).
            $headers[] = 'Authorization: Bearer ' . $this->config['api_key'];
            $payload = [
                'model' => $this->config['model'],
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ];
            if ($jsonMode) {
                $payload['response_format'] = ['type' => 'json_object'];
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);

        if ($response === false) {
            log_error('AIService HTTP error: ' . $error);
            return null;
        }

        $decoded = json_decode($response, true);

        // Captura de consumo reportado por el proveedor (formato OpenAI y
        // Anthropic): prompt/completion tokens y costo si viene incluido.
        $usage = $decoded['usage'] ?? null;
        if (is_array($usage)) {
            $this->ultimoUso = [
                'prompt_tokens' => (int) ($usage['prompt_tokens']
                    ?? $usage['input_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens']
                    ?? $usage['output_tokens'] ?? 0),
                'costo' => $usage['cost'] ?? null,
            ];
        }

        if ($this->config['provider'] === 'anthropic') {
            return $decoded['content'][0]['text'] ?? null;
        }
        return $decoded['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Tokens y costo de la última llamada a callChatCompletion(); null si el
     * proveedor no reportó usage o no hubo llamada.
     */
    public function ultimoConsumo(): ?array
    {
        return $this->ultimoUso;
    }

    private function logCall(string $endpoint, string $entrada, string $salida, int $latenciaMs): void
    {
        if ($this->organizacionId === null) {
            log_error("logs_ia omitido para '{$endpoint}': no se pudo determinar la organización.");
            return;
        }
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO logs_ia (organizacion_id, endpoint, entrada, salida, modelo, latencia_ms,
                    prompt_tokens, completion_tokens, usuario_id)
                 VALUES (:org, :endpoint, :entrada, :salida, :modelo, :latencia,
                    :prompt_tokens, :completion_tokens, :usuario_id)'
            );
            $stmt->execute([
                'org' => $this->organizacionId,
                'endpoint' => $endpoint,
                'entrada' => $entrada,
                'salida' => $salida,
                'modelo' => $this->config['model'],
                'latencia' => $latenciaMs,
                'prompt_tokens' => $this->ultimoUso['prompt_tokens'] ?? null,
                'completion_tokens' => $this->ultimoUso['completion_tokens'] ?? null,
                'usuario_id' => Auth::user()['id'] ?? null,
            ]);
            $this->ultimoUso = null;
        } catch (Throwable $e) {
            log_error('No se pudo registrar logs_ia: ' . $e->getMessage());
        }
    }
}
