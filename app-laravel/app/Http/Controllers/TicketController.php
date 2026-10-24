<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\Ticket;
use App\Models\OrdenTrabajo;
use App\Services\AIService;
use App\Services\RunbookService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with('activo')->orderByDesc('fecha_apertura');

        if ($estado = $request->get('estado')) {
            $query->where('estado', $estado);
        }

        $tickets = $query->paginate(20)->withQueryString();

        return view('modulos.tickets.index', compact('tickets'));
    }

    public function create()
    {
        $activos = Activo::orderBy('nombre')->get(['id', 'nombre']);

        return view('modulos.tickets.form', compact('activos'));
    }

    public function store(Request $request, AIService $ai, RunbookService $runbooks)
    {
        $data = $request->validate([
            'descripcion' => ['required', 'string'],
            'activo_id' => ['nullable', 'exists:activos,id'],
        ]);

        $organizacionId = auth()->user()->organizacion_id;
        $triage = $ai->triageTicket($data['descripcion']);

        $ticket = Ticket::create([
            'organizacion_id' => $organizacionId,
            'folio' => Ticket::generarFolio($organizacionId),
            'activo_id' => $data['activo_id'] ?? null,
            'reportado_por_id' => auth()->id(),
            'canal_origen' => 'web',
            'descripcion' => $data['descripcion'],
            'categoria_ia' => $triage['categoria'] ?? null,
            'prioridad_ia' => $triage['prioridad'] ?? null,
            'estado' => 'abierto',
            'fecha_apertura' => now(),
        ]);

        OrdenTrabajo::create([
            'organizacion_id' => $organizacionId,
            'ticket_id' => $ticket->id,
            'estado' => 'pendiente',
        ]);

        return redirect()->route('tickets.show', $ticket);
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('activo', 'ordenTrabajo');

        return view('modulos.tickets.show', compact('ticket'));
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'estado' => ['required', 'in:abierto,en_progreso,esperando_repuesto,cerrado,cancelado'],
            'prioridad_final' => ['nullable', 'in:baja,media,alta,critica'],
            'categoria_final' => ['nullable', 'string', 'max:60'],
        ]);

        $ticket->update([
            'estado' => $data['estado'],
            'prioridad_final' => $data['prioridad_final'] ?? $ticket->prioridad_final,
            'categoria_final' => $data['categoria_final'] ?? $ticket->categoria_final,
            'fecha_cierre' => $data['estado'] === 'cerrado' ? now() : null,
        ]);

        return redirect()->route('tickets.show', $ticket)->with('status', 'Ticket actualizado.');
    }
}
