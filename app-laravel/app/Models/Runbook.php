<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class Runbook extends Model
{
    use BelongsToOrganizacion;

    protected $fillable = [
        'organizacion_id', 'titulo', 'sintoma', 'causa', 'solucion', 'activo_id',
        'generado_por', 'validado', 'vigente', 'veces_aplicado', 'veces_exitoso', 'veces_fallido',
    ];

    protected $casts = [
        'validado' => 'boolean',
        'vigente' => 'boolean',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class);
    }

    public function aplicaciones()
    {
        return $this->hasMany(RunbookAplicacion::class);
    }
}
