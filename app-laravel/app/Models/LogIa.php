<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class LogIa extends Model
{
    use BelongsToOrganizacion;

    public $timestamps = false;

    protected $table = 'logs_ia';

    protected $fillable = [
        'organizacion_id', 'endpoint', 'entrada', 'salida', 'modelo', 'costo_estimado', 'latencia_ms', 'usuario_id',
    ];
}
