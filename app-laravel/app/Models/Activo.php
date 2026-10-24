<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class Activo extends Model
{
    use BelongsToOrganizacion;

    protected $fillable = [
        'organizacion_id', 'nombre', 'tipo', 'planta_id', 'ubicacion', 'ip_address',
        'nombre_profinet', 'version_firmware', 'fecha_puesta_marcha', 'estado', 'foto_path',
    ];

    protected $casts = [
        'fecha_puesta_marcha' => 'date',
    ];

    public function planta()
    {
        return $this->belongsTo(Planta::class);
    }

    public function diagnosticos()
    {
        return $this->hasMany(Diagnostico::class)->orderByDesc('fecha_diagnostico');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function productos()
    {
        return $this->belongsToMany(ProductoCatalogo::class, 'activo_producto', 'activo_id', 'producto_id')
            ->withPivot('cantidad');
    }
}
