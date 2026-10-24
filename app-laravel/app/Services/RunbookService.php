<?php

namespace App\Services;

use App\Models\Runbook;
use App\Models\RunbookAplicacion;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Motor de autoevolución de runbooks: cada aplicación real a un ticket
 * (éxito/fallo) ajusta contadores, y el runbook se promueve o se retira
 * solo, sin intervención humana. Port de app/models/RunbookModel.php.
 *  - Se valida (validado=1) al alcanzar 3 éxitos con tasa de éxito >= 70%.
 *  - Se retira (vigente=0) al alcanzar 3 aplicaciones con tasa de fallo >= 50%.
 */
class RunbookService
{
    private const MIN_APLICACIONES_PARA_DECIDIR = 3;
    private const UMBRAL_VALIDACION = 0.7;
    private const UMBRAL_RETIRO = 0.5;

    /**
     * Busca el mejor runbook vigente para un texto de descripción de falla,
     * usando coincidencia simple de palabras clave contra el síntoma.
     */
    public function sugerirPara(string $descripcion, ?int $activoId = null): ?Runbook
    {
        $palabras = collect(preg_split('/\s+/', mb_strtolower($descripcion)))
            ->filter(fn ($w) => mb_strlen($w) >= 4)
            ->values();

        if ($palabras->isEmpty()) {
            return null;
        }

        $query = Runbook::where('vigente', true)
            ->where(function ($q) use ($palabras) {
                foreach ($palabras as $palabra) {
                    $q->orWhere('sintoma', 'like', "%{$palabra}%");
                }
            });

        if ($activoId !== null) {
            $query->where(function ($q) use ($activoId) {
                $q->where('activo_id', $activoId)->orWhereNull('activo_id');
            });
        }

        return $query->orderByDesc('validado')->orderByDesc('veces_exitoso')->first();
    }

    public function crearDesdeTicket(Ticket $ticket, string $solucion, ?string $causa = null): Runbook
    {
        return Runbook::create([
            'organizacion_id' => $ticket->organizacion_id,
            'titulo' => \Illuminate\Support\Str::limit($ticket->descripcion, 100),
            'sintoma' => $ticket->descripcion,
            'causa' => $causa,
            'solucion' => $solucion,
            'activo_id' => $ticket->activo_id,
            'generado_por' => 'ia',
            'validado' => false,
            'vigente' => true,
        ]);
    }

    public function registrarAplicacion(Runbook $runbook, Ticket $ticket, bool $exitoso): void
    {
        DB::transaction(function () use ($runbook, $ticket, $exitoso) {
            RunbookAplicacion::create([
                'runbook_id' => $runbook->id,
                'ticket_id' => $ticket->id,
                'resultado' => $exitoso ? 'exitoso' : 'fallido',
            ]);

            $runbook->increment('veces_aplicado');
            $runbook->increment($exitoso ? 'veces_exitoso' : 'veces_fallido');
            $runbook->refresh();

            $this->evaluarPromocionORetiro($runbook);
        });
    }

    private function evaluarPromocionORetiro(Runbook $runbook): void
    {
        $total = $runbook->veces_aplicado;
        if ($total < self::MIN_APLICACIONES_PARA_DECIDIR) {
            return;
        }

        $tasaExito = $runbook->veces_exitoso / $total;
        $tasaFallo = $runbook->veces_fallido / $total;

        if ($tasaFallo >= self::UMBRAL_RETIRO) {
            $runbook->update(['vigente' => false]);

            return;
        }

        if ($tasaExito >= self::UMBRAL_VALIDACION) {
            $runbook->update(['validado' => true]);
        }
    }
}
