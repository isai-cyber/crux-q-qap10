<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\ApiToken;
use App\Models\OrdenTrabajo;
use App\Models\Planta;
use App\Models\ProductoCatalogo;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\AIService;
use App\Services\RunbookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API REST consumida por agentes externos (docs/API.md). Autenticación por
 * token Bearer (middleware api.token), no por sesión de navegador — todas
 * las consultas se aíslan EXPLÍCITAMENTE por la organización del token (los
 * global scopes de Eloquent no aplican aquí porque dependen de auth()->check(),
 * que es false en este contexto sin sesión web).
 */
class ApiController extends Controller
{
    public function crearTicket(Request $request)
    {
        $token = $this->token($request);
        $org = $token->organizacion_id;

        $data = $request->validate(['descripcion' => ['required', 'string']]);
        $descripcion = trim($data['descripcion']);

        $activoId = null;
        if ($request->filled('activo_id')) {
            $activoId = Activo::withoutGlobalScopes()->where('organizacion_id', $org)->find($request->input('activo_id'))?->id;
        } elseif ($request->filled('activo_nombre')) {
            $activoId = Activo::withoutGlobalScopes()->where('organizacion_id', $org)
                ->where('nombre', $request->input('activo_nombre'))->value('id');
        }

        $ai = new AIService($org);
        $triage = $ai->triageTicket($descripcion);

        $ticket = Ticket::create([
            'organizacion_id' => $org,
            'folio' => Ticket::generarFolio($org),
            'activo_id' => $activoId,
            'canal_origen' => $request->input('canal_origen', 'api'),
            'descripcion' => $descripcion,
            'categoria_ia' => $triage['categoria'] ?? null,
            'prioridad_ia' => $triage['prioridad'] ?? null,
            'estado' => 'abierto',
            'fecha_apertura' => now(),
        ]);
        OrdenTrabajo::create(['organizacion_id' => $org, 'ticket_id' => $ticket->id, 'estado' => 'pendiente']);

        $runbook = (new RunbookService)->sugerirPara($descripcion, $activoId);

        return response()->json([
            'ticket' => $ticket,
            'triage' => $triage,
            'runbook_sugerido' => $runbook,
        ], 201);
    }

    public function consultarActivo(Request $request, int $id)
    {
        $token = $this->token($request);
        $activo = Activo::withoutGlobalScopes()->where('organizacion_id', $token->organizacion_id)
            ->with('diagnosticos')->find($id);

        if (! $activo) {
            return response()->json(['error' => 'Activo no encontrado.'], 404);
        }

        return response()->json($activo);
    }

    public function crearActivo(Request $request)
    {
        $token = $this->token($request);
        $org = $token->organizacion_id;

        $data = $request->validate([
            'nombre' => ['required', 'string'],
            'planta_id' => ['required'],
        ]);

        if (! Planta::withoutGlobalScopes()->where('organizacion_id', $org)->where('id', $data['planta_id'])->exists()) {
            return response()->json(['error' => 'planta_id no pertenece a tu organización.'], 422);
        }

        $activo = Activo::create(array_merge($request->all(), ['organizacion_id' => $org]));

        return response()->json($activo, 201);
    }

    public function crearUsuario(Request $request)
    {
        $token = $this->token($request);
        $org = $token->organizacion_id;

        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', 'in:admin,supervisor,tecnico'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->all()], 422);
        }

        if (Usuario::withoutGlobalScopes()->where('organizacion_id', $org)->where('email', $request->input('email'))->exists()) {
            return response()->json(['errors' => ['Ya existe un usuario con ese email en tu organización.']], 422);
        }

        $usuario = Usuario::create([
            'organizacion_id' => $org,
            'nombre' => $request->input('nombre'),
            'email' => $request->input('email'),
            'password_hash' => bcrypt($request->input('password')),
            'rol' => $request->input('rol'),
            'activo' => true,
        ]);

        return response()->json($usuario->makeHidden('password_hash'), 201);
    }

    public function buscarCatalogo(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $query = ProductoCatalogo::query();
        if ($q !== '') {
            $query->where(fn ($sub) => $sub->where('numero_parte', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%"));
        }

        return response()->json($query->limit(20)->get());
    }

    public function alertasPendientes(Request $request)
    {
        $token = $this->token($request);
        $org = $token->organizacion_id;

        $tickets = Ticket::withoutGlobalScopes()->where('organizacion_id', $org)
            ->where('estado', 'abierto')
            ->where(function ($q) {
                $q->where('prioridad_final', 'critica')
                    ->orWhere(fn ($q2) => $q2->whereNull('prioridad_final')->where('prioridad_ia', 'critica'));
            })->orderBy('fecha_apertura')->get(['id', 'folio', 'descripcion', 'prioridad_ia', 'fecha_apertura']);

        $stock = ProductoCatalogo::whereDoesntHave('inventarios', function ($q) use ($org) {
            $q->where('organizacion_id', $org)->where('stock_actual', '>', 0);
        })->get(['id', 'numero_parte', 'descripcion']);

        return response()->json([
            'tickets_criticos_sin_atender' => $tickets,
            'productos_sin_stock' => $stock,
        ]);
    }

    private function token(Request $request): ApiToken
    {
        return $request->attributes->get('api_token');
    }
}
