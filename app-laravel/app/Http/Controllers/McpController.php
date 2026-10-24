<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\ApiToken;
use App\Models\Planta;
use App\Models\ProductoCatalogo;
use App\Models\Ticket;
use App\Models\OrdenTrabajo;
use App\Models\Usuario;
use App\Services\AIService;
use App\Services\RunbookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Servidor MCP (Model Context Protocol) en Laravel: JSON-RPC 2.0 puro sobre
 * HTTP. Expone como "tools" las mismas 6 operaciones que la API REST
 * (docs/MCP.md), para Hermes-equivalentes/Claude/cualquier cliente MCP.
 */
class McpController extends Controller
{
    private const HERRAMIENTAS = [
        'crear_ticket' => ['permiso' => 'crear_ticket', 'description' => 'Crea un ticket de falla y lo clasifica automáticamente con IA.'],
        'consultar_activo' => ['permiso' => 'consultar_activos', 'description' => 'Consulta un activo por ID, incluyendo su historial de diagnósticos.'],
        'crear_activo' => ['permiso' => 'crear_activo', 'description' => 'Da de alta un activo (equipo) en el CMDB.'],
        'crear_usuario' => ['permiso' => 'crear_usuario', 'description' => 'Crea un usuario (técnico, supervisor o admin).'],
        'buscar_catalogo' => ['permiso' => 'consultar_catalogo', 'description' => 'Busca productos en el catálogo Siemens.'],
        'alertas_pendientes' => ['permiso' => 'consultar_alertas', 'description' => 'Tickets críticos sin atender y productos sin stock.'],
    ];

    public function handle(Request $request)
    {
        $body = $request->json()->all();
        if (! isset($body['jsonrpc'], $body['method'])) {
            return $this->respuesta(null, null, ['code' => -32600, 'message' => 'Solicitud JSON-RPC inválida.']);
        }

        $id = $body['id'] ?? null;

        try {
            $resultado = match ($body['method']) {
                'initialize' => $this->initialize(),
                'tools/list' => $this->listarHerramientas(),
                'tools/call' => $this->ejecutarHerramienta($request, $body['params'] ?? []),
                default => throw new \RuntimeException("Método no soportado: {$body['method']}"),
            };

            return $this->respuesta($id, $resultado, null);
        } catch (\Throwable $e) {
            return $this->respuesta($id, null, ['code' => -32000, 'message' => $e->getMessage()]);
        }
    }

    private function initialize(): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'serverInfo' => ['name' => 'sirius-maintenance-hub-mcp', 'version' => '2.0.0'],
            'capabilities' => ['tools' => new \stdClass],
        ];
    }

    private function listarHerramientas(): array
    {
        $tools = [];
        foreach (self::HERRAMIENTAS as $nombre => $def) {
            $tools[] = ['name' => $nombre, 'description' => $def['description'], 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass]];
        }

        return ['tools' => $tools];
    }

    private function ejecutarHerramienta(Request $request, array $params): array
    {
        $nombre = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        if (! isset(self::HERRAMIENTAS[$nombre])) {
            throw new \RuntimeException("Herramienta desconocida: {$nombre}");
        }

        $token = $this->autenticar($request, self::HERRAMIENTAS[$nombre]['permiso']);
        $org = $token->organizacion_id;

        $resultado = match ($nombre) {
            'crear_ticket' => $this->herramientaCrearTicket($org, $args),
            'consultar_activo' => $this->herramientaConsultarActivo($org, $args),
            'crear_activo' => $this->herramientaCrearActivo($org, $args),
            'crear_usuario' => $this->herramientaCrearUsuario($org, $args),
            'buscar_catalogo' => $this->herramientaBuscarCatalogo($args),
            'alertas_pendientes' => $this->herramientaAlertasPendientes($org),
        };

        return ['content' => [['type' => 'text', 'text' => json_encode($resultado, JSON_UNESCAPED_UNICODE)]]];
    }

    private function autenticar(Request $request, string $permiso): ApiToken
    {
        $header = $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            throw new \RuntimeException('Falta el token de autorización.');
        }
        $token = ApiToken::where('token_hash', hash('sha256', $m[1]))->where('activo', true)->first();
        if (! $token) {
            throw new \RuntimeException('Token inválido o revocado.');
        }
        if (! $token->tienePermiso($permiso)) {
            throw new \RuntimeException("El token no tiene el permiso '{$permiso}'.");
        }
        $token->update(['ultimo_uso' => now()]);

        return $token;
    }

    private function herramientaCrearTicket(int $org, array $args): array
    {
        $descripcion = trim($args['descripcion'] ?? '');
        if ($descripcion === '') {
            throw new \RuntimeException('descripcion es obligatoria.');
        }

        $activoId = null;
        if (! empty($args['activo_nombre'])) {
            $activoId = Activo::withoutGlobalScopes()->where('organizacion_id', $org)->where('nombre', $args['activo_nombre'])->value('id');
        }

        $ai = new AIService($org);
        $triage = $ai->triageTicket($descripcion);

        $ticket = Ticket::create([
            'organizacion_id' => $org,
            'folio' => Ticket::generarFolio($org),
            'activo_id' => $activoId,
            'canal_origen' => $args['canal_origen'] ?? 'api',
            'descripcion' => $descripcion,
            'categoria_ia' => $triage['categoria'] ?? null,
            'prioridad_ia' => $triage['prioridad'] ?? null,
            'estado' => 'abierto',
            'fecha_apertura' => now(),
        ]);
        OrdenTrabajo::create(['organizacion_id' => $org, 'ticket_id' => $ticket->id, 'estado' => 'pendiente']);

        $runbook = (new RunbookService)->sugerirPara($descripcion, $activoId);

        return ['ticket' => $ticket, 'triage' => $triage, 'runbook_sugerido' => $runbook];
    }

    private function herramientaConsultarActivo(int $org, array $args): array
    {
        $activo = Activo::withoutGlobalScopes()->where('organizacion_id', $org)->with('diagnosticos')->find($args['id'] ?? 0);
        if (! $activo) {
            throw new \RuntimeException('Activo no encontrado.');
        }

        return $activo->toArray();
    }

    private function herramientaCrearActivo(int $org, array $args): array
    {
        if (empty($args['nombre']) || empty($args['planta_id'])) {
            throw new \RuntimeException('nombre y planta_id son obligatorios.');
        }
        if (! Planta::withoutGlobalScopes()->where('organizacion_id', $org)->where('id', $args['planta_id'])->exists()) {
            throw new \RuntimeException('planta_id no pertenece a esta organización.');
        }

        return Activo::create(array_merge($args, ['organizacion_id' => $org]))->toArray();
    }

    private function herramientaCrearUsuario(int $org, array $args): array
    {
        $validator = Validator::make($args, [
            'nombre' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', 'in:admin,supervisor,tecnico'],
        ]);
        if ($validator->fails()) {
            throw new \RuntimeException(implode(' ', $validator->errors()->all()));
        }

        $usuario = Usuario::create([
            'organizacion_id' => $org,
            'nombre' => $args['nombre'],
            'email' => $args['email'],
            'password_hash' => bcrypt($args['password']),
            'rol' => $args['rol'],
            'activo' => true,
        ]);

        return $usuario->makeHidden('password_hash')->toArray();
    }

    private function herramientaBuscarCatalogo(array $args): array
    {
        $q = trim($args['q'] ?? '');
        $query = ProductoCatalogo::query();
        if ($q !== '') {
            $query->where(fn ($sub) => $sub->where('numero_parte', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%"));
        }

        return $query->limit(20)->get()->toArray();
    }

    private function herramientaAlertasPendientes(int $org): array
    {
        $tickets = Ticket::withoutGlobalScopes()->where('organizacion_id', $org)->where('estado', 'abierto')
            ->where(fn ($q) => $q->where('prioridad_final', 'critica')->orWhere(fn ($q2) => $q2->whereNull('prioridad_final')->where('prioridad_ia', 'critica')))
            ->orderBy('fecha_apertura')->get(['id', 'folio', 'descripcion', 'prioridad_ia', 'fecha_apertura'])->toArray();

        $stock = ProductoCatalogo::whereDoesntHave('inventarios', fn ($q) => $q->where('organizacion_id', $org)->where('stock_actual', '>', 0))
            ->get(['id', 'numero_parte', 'descripcion'])->toArray();

        return ['tickets_criticos_sin_atender' => $tickets, 'productos_sin_stock' => $stock];
    }

    private function respuesta(mixed $id, mixed $result, ?array $error)
    {
        $payload = ['jsonrpc' => '2.0', 'id' => $id];
        if ($error !== null) {
            $payload['error'] = $error;
        } else {
            $payload['result'] = $result;
        }

        return response()->json($payload);
    }
}
