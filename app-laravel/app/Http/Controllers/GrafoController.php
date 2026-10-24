<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\BaseConocimientoChunk;
use App\Models\DisenoTecnico;
use App\Models\Runbook;
use App\Models\RunbookAplicacion;
use App\Models\Ticket;

class GrafoController extends Controller
{
    public function index()
    {
        $grafo = $this->construirGrafo();

        return view('modulos.grafo.index', compact('grafo'));
    }

    private function construirGrafo(): array
    {
        $nodes = [];
        $links = [];

        foreach (Activo::all(['id', 'nombre']) as $a) {
            $nodes[] = ['id' => "activo-{$a->id}", 'label' => $a->nombre, 'tipo' => 'activo', 'val' => 6];
        }

        foreach (Ticket::orderByDesc('fecha_apertura')->limit(60)->get(['id', 'folio', 'activo_id']) as $t) {
            $nodes[] = ['id' => "ticket-{$t->id}", 'label' => $t->folio, 'tipo' => 'ticket', 'val' => 3];
            if ($t->activo_id) {
                $links[] = ['source' => "ticket-{$t->id}", 'target' => "activo-{$t->activo_id}", 'tipo' => 'reporta_falla_en'];
            }
        }

        foreach (Runbook::all(['id', 'titulo', 'activo_id', 'validado', 'vigente', 'veces_aplicado', 'veces_exitoso']) as $r) {
            $nodes[] = [
                'id' => "runbook-{$r->id}",
                'label' => $r->titulo,
                'tipo' => $r->vigente ? ($r->validado ? 'runbook_validado' : 'runbook_sugerido') : 'runbook_retirado',
                'val' => 4 + min($r->veces_aplicado, 10),
            ];
            if ($r->activo_id) {
                $links[] = ['source' => "runbook-{$r->id}", 'target' => "activo-{$r->activo_id}", 'tipo' => 'aplica_a'];
            }
        }

        foreach (RunbookAplicacion::whereHas('runbook')->get(['runbook_id', 'ticket_id', 'resultado']) as $ap) {
            $links[] = [
                'source' => "runbook-{$ap->runbook_id}",
                'target' => "ticket-{$ap->ticket_id}",
                'tipo' => $ap->resultado === 'exitoso' ? 'resolvio' : 'no_resolvio',
            ];
        }

        foreach (DisenoTecnico::all(['id', 'titulo', 'activo_id']) as $d) {
            $nodes[] = ['id' => "diseno-{$d->id}", 'label' => $d->titulo, 'tipo' => 'diseno', 'val' => 3];
            if ($d->activo_id) {
                $links[] = ['source' => "diseno-{$d->id}", 'target' => "activo-{$d->activo_id}", 'tipo' => 'documenta'];
            }
        }

        // Documentos fuente de la base de conocimiento: GLOBALES.
        foreach (BaseConocimientoChunk::select('documento_origen')->distinct()->pluck('documento_origen') as $doc) {
            $nodes[] = ['id' => 'doc-'.md5($doc), 'label' => $doc, 'tipo' => 'fuente_conocimiento', 'val' => 5];
        }

        return ['nodes' => $nodes, 'links' => $links];
    }
}
