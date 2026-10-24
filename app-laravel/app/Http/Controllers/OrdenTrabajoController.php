<?php

namespace App\Http\Controllers;

use App\Models\OrdenTrabajo;
use App\Models\Runbook;
use App\Models\Usuario;
use App\Services\RunbookService;
use Illuminate\Http\Request;

class OrdenTrabajoController extends Controller
{
    public function index()
    {
        $ordenes = OrdenTrabajo::with('ticket', 'tecnico')->orderByDesc('created_at')->paginate(20);

        return view('modulos.ordenes.index', compact('ordenes'));
    }

    public function show(OrdenTrabajo $orden, RunbookService $runbooks)
    {
        $orden->load('ticket');

        $tecnicos = Usuario::deMiOrganizacion()->where('rol', 'tecnico')->where('activo', true)->orderBy('nombre')->get();

        $runbookSugerido = $orden->runbook_id
            ? null
            : $runbooks->sugerirPara($orden->ticket->descripcion, $orden->ticket->activo_id);

        $runbooksDisponibles = Runbook::where('vigente', true)
            ->orderByDesc('validado')->orderByDesc('veces_exitoso')->limit(30)->get(['id', 'titulo']);

        return view('modulos.ordenes.show', compact('orden', 'tecnicos', 'runbookSugerido', 'runbooksDisponibles'));
    }

    public function update(Request $request, OrdenTrabajo $orden, RunbookService $runbookService)
    {
        $data = $request->validate([
            'tecnico_id' => ['nullable', 'exists:usuarios,id'],
            'runbook_id' => ['nullable', 'exists:runbooks,id'],
            'diagnostico' => ['nullable', 'string'],
            'solucion_aplicada' => ['nullable', 'string'],
            'solucion_exitosa' => ['nullable', 'in:0,1'],
            'tiempo_invertido_minutos' => ['nullable', 'integer', 'min:0'],
            'estado' => ['required', 'in:pendiente,en_progreso,completada'],
        ]);

        $orden->update($data);

        if ($data['estado'] === 'completada') {
            $this->cerrarYEvolucionar($orden, $data, $runbookService);
        }

        return redirect()->route('ordenes.show', $orden)->with('status', 'Orden actualizada.');
    }

    private function cerrarYEvolucionar(OrdenTrabajo $orden, array $data, RunbookService $runbookService): void
    {
        $orden->loadMissing('ticket', 'runbook');

        if ($orden->runbook_id && isset($data['solucion_exitosa'])) {
            $runbookService->registrarAplicacion($orden->runbook, $orden->ticket, $data['solucion_exitosa'] === '1');
        } elseif (! $orden->runbook_id && filled($orden->solucion_aplicada)) {
            $runbookService->crearDesdeTicket($orden->ticket, $orden->solucion_aplicada, $orden->diagnostico);
        }

        $orden->ticket->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);
    }
}
