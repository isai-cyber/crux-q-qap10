<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\ChatMensaje;
use App\Models\Ticket;
use App\Models\OrdenTrabajo;
use App\Models\Usuario;
use App\Services\AIService;
use App\Services\RunbookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * ORION: asistente de chat interno (reemplaza a WhatsApp como canal
 * conversacional). Port de app/controllers/ChatController.php.
 */
class ChatController extends Controller
{
    public const NOMBRE_ASISTENTE = 'ORION';

    public function index()
    {
        $historial = ChatMensaje::with('ticket')->where('usuario_id', auth()->id())
            ->orderBy('created_at')->limit(100)->get();
        $activos = Activo::orderBy('nombre')->get(['id', 'nombre']);

        return view('modulos.chat.index', compact('historial', 'activos'));
    }

    public function enviar(Request $request, AIService $ai, RunbookService $runbookService)
    {
        $mensaje = trim((string) $request->input('mensaje'));
        if ($mensaje === '') {
            return response()->json(['error' => 'Escribe una descripción de la falla.'], 422);
        }

        $usuario = auth()->user();
        $org = $usuario->organizacion_id;
        $this->guardarMensaje($org, $usuario->id, 'usuario', $mensaje, null);

        if ($this->pareceGestionDeUsuarios($mensaje)) {
            $respuesta = $this->manejarGestionUsuarios($org, $usuario, $mensaje, $ai);
            $this->guardarMensaje($org, $usuario->id, 'asistente', $respuesta, null);

            return response()->json(['respuesta' => $respuesta, 'ticket' => null]);
        }

        if ($this->pareceConsultaDeManual($mensaje)) {
            $resultado = $ai->responderConManuales($mensaje);
            $this->guardarMensaje($org, $usuario->id, 'asistente', $resultado['respuesta'], null);

            return response()->json(['respuesta' => $resultado['respuesta'], 'ticket' => null]);
        }

        return response()->json($this->manejarReporteFalla($org, $usuario, $mensaje, $request, $ai, $runbookService));
    }

    private function manejarReporteFalla(int $org, Usuario $usuario, string $mensaje, Request $request, AIService $ai, RunbookService $runbookService): array
    {
        $activoId = null;
        if ($request->filled('activo_id')) {
            $activo = Activo::find($request->input('activo_id'));
            $activoId = $activo?->id;
        }

        $triage = $ai->triageTicket($mensaje);

        $ticket = Ticket::create([
            'organizacion_id' => $org,
            'folio' => Ticket::generarFolio($org),
            'activo_id' => $activoId,
            'reportado_por_id' => $usuario->id,
            'canal_origen' => 'chat',
            'descripcion' => $mensaje,
            'categoria_ia' => $triage['categoria'] ?? null,
            'prioridad_ia' => $triage['prioridad'] ?? null,
            'estado' => 'abierto',
            'fecha_apertura' => now(),
        ]);

        OrdenTrabajo::create(['organizacion_id' => $org, 'ticket_id' => $ticket->id, 'estado' => 'pendiente']);

        $runbook = $runbookService->sugerirPara($mensaje, $activoId);

        $respuesta = $this->componerRespuestaTicket($ticket, $triage, $runbook);
        $this->guardarMensaje($org, $usuario->id, 'asistente', $respuesta, $ticket->id);

        return ['respuesta' => $respuesta, 'ticket' => ['id' => $ticket->id, 'folio' => $ticket->folio]];
    }

    private function pareceGestionDeUsuarios(string $mensaje): bool
    {
        $texto = mb_strtolower($mensaje);
        $tieneAccion = (bool) preg_match('/\b(crea|crear|agregar|añad[ei]r?|dar de alta|alta de|nuevo|nueva)\b/u', $texto);
        $tieneSujeto = (bool) preg_match('/\b(usuario|tecnico|técnico|supervisor|administrador|admin|empleado|rol)\b/u', $texto);

        return $tieneAccion && $tieneSujeto;
    }

    private function pareceConsultaDeManual(string $mensaje): bool
    {
        $texto = mb_strtolower($mensaje);
        $esPregunta = (bool) preg_match('/^(qué|que|cómo|como|cuál|cual|dónde|donde|para qué|para que)\b/u', $texto)
            || str_ends_with(trim($texto), '?');
        $mencionaManual = (bool) preg_match('/\b(manual|sinumerik|step\s*7|profinet|simatic|automation tool|operate)\b/u', $texto);

        return $esPregunta || $mencionaManual;
    }

    private function manejarGestionUsuarios(int $org, Usuario $usuario, string $mensaje, AIService $ai): string
    {
        if (! $usuario->esAdmin()) {
            return 'Solo un administrador puede crear usuarios desde '.self::NOMBRE_ASISTENTE.'. '
                .'Pídele a un admin de tu organización que lo haga, o hazlo desde Administración → Usuarios.';
        }

        $datos = $ai->extraerDatosUsuario($mensaje) ?? $this->extraerDatosUsuarioRegex($mensaje);
        if ($datos === null) {
            return 'No pude entender los datos del nuevo usuario. Usa este formato: '
                .'"crear usuario: Nombre Apellido, email@empresa.com, tecnico[, contraseña]" '
                .'(rol: admin, supervisor o tecnico).';
        }

        $validator = Validator::make($datos, [
            'nombre' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
            'rol' => ['required', 'in:admin,supervisor,tecnico'],
        ]);

        if ($validator->fails()) {
            return 'No se pudo crear el usuario: '.implode(' ', $validator->errors()->all());
        }

        if (Usuario::withoutGlobalScopes()->where('organizacion_id', $org)->where('email', $datos['email'])->exists()) {
            return 'No se pudo crear el usuario: ya existe un usuario con ese email en tu organización.';
        }

        Usuario::create([
            'organizacion_id' => $org,
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'password_hash' => bcrypt($datos['password']),
            'rol' => $datos['rol'],
            'activo' => true,
        ]);

        return "Listo, creé el usuario \"{$datos['nombre']}\" ({$datos['email']}) con rol \"{$datos['rol']}\". "
            .'Ya puede iniciar sesión con la contraseña indicada.';
    }

    private function extraerDatosUsuarioRegex(string $mensaje): ?array
    {
        if (! preg_match('/:(.+)$/u', $mensaje, $m)) {
            return null;
        }
        $partes = array_map('trim', explode(',', $m[1]));
        if (count($partes) < 3) {
            return null;
        }

        [$nombre, $email, $rolTexto] = $partes;
        $password = $partes[3] ?? bin2hex(random_bytes(4));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $rol = match (true) {
            str_contains(mb_strtolower($rolTexto), 'admin') => 'admin',
            str_contains(mb_strtolower($rolTexto), 'supervisor') => 'supervisor',
            default => 'tecnico',
        };

        return ['nombre' => $nombre, 'email' => $email, 'rol' => $rol, 'password' => $password];
    }

    private function componerRespuestaTicket(Ticket $ticket, array $triage, $runbook): string
    {
        $partes = [];
        $partes[] = "He registrado el ticket {$ticket->folio}, clasificado como prioridad "
            ."\"{$triage['prioridad']}\" (categoría: {$triage['categoria']}).";

        if ($runbook !== null) {
            $partes[] = 'Encontré un procedimiento'.($runbook->validado ? ' ya validado' : ' sugerido')
                ." para un caso similar: \"{$runbook->titulo}\" → {$runbook->solucion}";
        } else {
            $partes[] = 'Aún no hay un procedimiento previo para un caso similar; se creó una orden de trabajo pendiente para que un técnico lo revise.';
        }

        return implode(' ', $partes);
    }

    private function guardarMensaje(int $org, int $usuarioId, string $rol, string $contenido, ?int $ticketId): void
    {
        ChatMensaje::create([
            'organizacion_id' => $org,
            'usuario_id' => $usuarioId,
            'rol' => $rol,
            'contenido' => $contenido,
            'ticket_id' => $ticketId,
        ]);
    }
}
