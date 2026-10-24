<?php

namespace App\Services;

use App\Models\BaseConocimientoChunk;
use App\Models\LogIa;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador a un proveedor de IA generativa (DeepSeek por defecto, también
 * OpenAI/Anthropic) configurado vía .env. Port de app/services/AIService.php
 * de la versión PHP pura, usando el cliente Http de Laravel en vez de cURL
 * crudo y modelos Eloquent en vez de PDO.
 *
 * Autoevolución: triageTicket() incluye como ejemplos few-shot los últimos
 * tickets que un supervisor corrigió (categoria_final/prioridad_final) DE LA
 * MISMA ORGANIZACIÓN — nunca se filtran ejemplos de otra organización.
 */
class AIService
{
    private const PERSONA = 'Eres ORION, el asistente técnico de un CMMS (sistema de gestión de mantenimiento) '
        .'especializado en automatización industrial: PLCs, controladores SIMATIC/SINUMERIK, arrancadores y '
        .'contactores SIRIUS, y mantenimiento de plantas. Responde con precisión técnica, en español, breve y '
        .'accionable para un técnico de mantenimiento o un supervisor de planta.';

    private array $config;
    private ?int $organizacionId;

    public function __construct(?int $organizacionId = null)
    {
        $this->config = config('ai');
        $this->organizacionId = $organizacionId ?? auth()->user()?->organizacion_id;
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key']);
    }

    public function triageTicket(string $descripcion): array
    {
        if (! $this->isConfigured()) {
            return $this->triageFallback($descripcion);
        }

        $ejemplos = $this->obtenerEjemplosValidados();
        $bloqueEjemplos = $ejemplos === ''
            ? ''
            : "Ejemplos reales ya corregidos por un supervisor (usa el mismo criterio):\n{$ejemplos}\n\n";

        $prompt = self::PERSONA."\n\nClasifica el siguiente reporte de falla de un equipo industrial. "
            ."Responde SOLO JSON con las claves categoria, prioridad (baja|media|alta|critica) y razon.\n\n"
            .$bloqueEjemplos
            ."Reporte: {$descripcion}";

        $start = microtime(true);
        $respuesta = $this->callChatCompletion($prompt, true);
        $latencia = (int) ((microtime(true) - $start) * 1000);

        $data = json_decode($respuesta ?? '', true);
        if (! is_array($data) || ! isset($data['categoria'], $data['prioridad'])) {
            $data = $this->triageFallback($descripcion);
        }

        $this->logCall('triage_ticket', $descripcion, $respuesta ?? '', $latencia);

        return $data;
    }

    public function generateDiagram(string $descripcion, array $productos = []): string
    {
        if (! $this->isConfigured()) {
            return $this->diagramaFallback($descripcion);
        }

        $listaProductos = $productos
            ? "Componentes del catálogo a incluir:\n".collect($productos)
                ->map(fn ($p) => "- {$p['numero_parte']}: {$p['descripcion']}")->implode("\n")."\n\n"
            : '';

        $prompt = 'Genera un diagrama esquemático simple en SVG (solo el código <svg>...</svg>, '
            .'sin explicación, sin markdown, viewBox 0 0 600 400, formas básicas y texto legible) '
            ."para el siguiente requerimiento de control/automatización industrial.\n\n"
            .$listaProductos
            ."Requerimiento: {$descripcion}";

        $start = microtime(true);
        $respuesta = $this->callChatCompletion($prompt, false);
        $latencia = (int) ((microtime(true) - $start) * 1000);
        $this->logCall('generate_diagram', $descripcion, $respuesta ?? '', $latencia);

        return $this->extraerSvg($respuesta ?? '') ?? $this->diagramaFallback($descripcion);
    }

    public function generateTechDoc(string $descripcion, array $contexto = []): string
    {
        if (! $this->isConfigured()) {
            return $this->documentoFallback($descripcion);
        }

        $bloqueContexto = $contexto
            ? "Contexto adicional:\n".collect($contexto)
                ->map(fn ($v, $k) => "- {$k}: {$v}")->implode("\n")."\n\n"
            : '';

        $prompt = 'Redacta un documento técnico en Markdown para mantenimiento industrial, con las '
            .'secciones: ## Alcance, ## Materiales (BOM en tabla), ## Procedimiento (pasos numerados) '
            ."y ## Notas de seguridad. Sé conciso y técnico.\n\n"
            .$bloqueContexto
            ."Solicitud: {$descripcion}";

        $start = microtime(true);
        $respuesta = $this->callChatCompletion($prompt, false);
        $latencia = (int) ((microtime(true) - $start) * 1000);
        $this->logCall('generate_tech_doc', $descripcion, $respuesta ?? '', $latencia);

        return filled($respuesta) ? $respuesta : $this->documentoFallback($descripcion);
    }

    public function extraerDatosUsuario(string $mensaje): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $prompt = 'Extrae de este mensaje los datos para crear un usuario del sistema. '
            .'Responde SOLO JSON con las claves nombre, email, rol (admin|supervisor|tecnico) y password '
            .'(si no se indica contraseña, inventa una aleatoria de 10 caracteres). '
            ."Si falta el nombre o el email, responde {\"error\": true}.\n\nMensaje: {$mensaje}";

        $respuesta = $this->callChatCompletion($prompt, true);
        $this->logCall('extraer_datos_usuario', $mensaje, $respuesta ?? '', 0);

        $data = json_decode($respuesta ?? '', true);
        if (! is_array($data) || ! empty($data['error']) || empty($data['nombre']) || empty($data['email'])) {
            return null;
        }
        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $data['rol'] = in_array($data['rol'] ?? '', ['admin', 'supervisor', 'tecnico'], true) ? $data['rol'] : 'tecnico';
        $data['password'] = $data['password'] ?? bin2hex(random_bytes(5));

        return $data;
    }

    /**
     * RAG sobre base_conocimiento_chunks (GLOBAL). Recuperación por palabras
     * clave (sin necesidad de embeddings); si hay IA configurada, sintetiza
     * la respuesta citando la fuente.
     */
    public function responderConManuales(string $pregunta, int $limite = 4): array
    {
        $palabras = collect(preg_split('/\s+/', mb_strtolower($pregunta)))
            ->filter(fn ($w) => mb_strlen($w) >= 4)
            ->values();

        $fragmentos = collect();
        if ($palabras->isNotEmpty()) {
            $fragmentos = BaseConocimientoChunk::where(function ($query) use ($palabras) {
                foreach ($palabras as $palabra) {
                    $query->orWhere('texto', 'like', "%{$palabra}%");
                }
            })->limit($limite)->get();
        }

        if ($fragmentos->isEmpty()) {
            return [
                'respuesta' => 'No encontré información sobre eso en los manuales indexados todavía. '
                    .'La base de conocimiento tiene solo una muestra de BE2 (SINUMERIK) y SAT (SIMATIC Automation Tool); '
                    .'para cubrir más temas hace falta ingestar más contenido de esos manuales.',
                'fuentes' => [],
            ];
        }

        if (! $this->isConfigured()) {
            $texto = $fragmentos->map(fn ($f) => "[{$f->documento_origen} — {$f->capitulo}]\n{$f->texto}")->implode("\n\n");

            return ['respuesta' => "Encontré esto en los manuales:\n\n{$texto}", 'fuentes' => $fragmentos];
        }

        $contexto = $fragmentos->map(fn ($f) => "Fuente: {$f->documento_origen} — {$f->capitulo}\n{$f->texto}")->implode("\n\n");
        $prompt = self::PERSONA."\n\nResponde la siguiente pregunta usando SOLO la información de estos fragmentos "
            ."de manuales técnicos, citando el documento fuente. Si los fragmentos no alcanzan para responder, dilo.\n\n"
            ."Fragmentos:\n{$contexto}\n\nPregunta: {$pregunta}";

        $start = microtime(true);
        $respuesta = $this->callChatCompletion($prompt, false);
        $latencia = (int) ((microtime(true) - $start) * 1000);
        $this->logCall('rag_manuales', $pregunta, $respuesta ?? '', $latencia);

        return ['respuesta' => $respuesta ?? 'No pude generar una respuesta.', 'fuentes' => $fragmentos];
    }

    private function obtenerEjemplosValidados(int $limite = 5): string
    {
        if ($this->organizacionId === null) {
            return '';
        }

        $filas = Ticket::withoutGlobalScopes()
            ->where('organizacion_id', $this->organizacionId)
            ->whereNotNull('categoria_final')
            ->whereNotNull('prioridad_final')
            ->orderByDesc('updated_at')
            ->limit($limite)
            ->get(['descripcion', 'categoria_final', 'prioridad_final']);

        if ($filas->isEmpty()) {
            return '';
        }

        return $filas->map(
            fn ($f) => "- Reporte: \"{$f->descripcion}\" → categoria={$f->categoria_final}, prioridad={$f->prioridad_final}"
        )->implode("\n");
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
        return "## Documento no disponible\n\nConfigure `AI_API_KEY` en `.env` para generar documentos técnicos con IA.\n\n"
            ."**Solicitud registrada:** {$descripcion}";
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

        try {
            if ($this->config['provider'] === 'anthropic') {
                $response = Http::withHeaders([
                    'x-api-key' => $this->config['api_key'],
                    'anthropic-version' => '2023-06-01',
                ])->timeout(30)->post($url, [
                    'model' => $this->config['model'],
                    'max_tokens' => 1500,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

                return $response->json('content.0.text');
            }

            $payload = [
                'model' => $this->config['model'],
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ];
            if ($jsonMode) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $response = Http::withToken($this->config['api_key'])->timeout(30)->post($url, $payload);

            return $response->json('choices.0.message.content');
        } catch (\Throwable $e) {
            Log::error('AIService HTTP error: '.$e->getMessage());

            return null;
        }
    }

    private function logCall(string $endpoint, string $entrada, string $salida, int $latenciaMs): void
    {
        if ($this->organizacionId === null) {
            Log::warning("logs_ia omitido para '{$endpoint}': no se pudo determinar la organización.");

            return;
        }

        try {
            LogIa::create([
                'organizacion_id' => $this->organizacionId,
                'endpoint' => $endpoint,
                'entrada' => $entrada,
                'salida' => $salida,
                'modelo' => $this->config['model'],
                'latencia_ms' => $latenciaMs,
                'usuario_id' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo registrar logs_ia: '.$e->getMessage());
        }
    }
}
