<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class OrdenTrabajo extends Model
{
    use BelongsToOrganizacion;

    protected $table = 'ordenes_trabajo';

    protected $fillable = [
        'organizacion_id', 'ticket_id', 'tecnico_id', 'runbook_id', 'diagnostico',
        'solucion_aplicada', 'solucion_exitosa', 'tiempo_invertido_minutos', 'estado',
    ];

    protected $casts = [
        'solucion_exitosa' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function tecnico()
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }

    public function runbook()
    {
        return $this->belongsTo(Runbook::class);
    }

    public function repuestos()
    {
        return $this->hasMany(OrdenTrabajoRepuesto::class, 'orden_trabajo_id');
    }
}
