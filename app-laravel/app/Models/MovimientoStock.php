<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    use BelongsToOrganizacion;

    protected $table = 'movimientos_stock';

    public $timestamps = false;

    protected $fillable = [
        'organizacion_id', 'producto_id', 'tipo', 'cantidad', 'orden_trabajo_id', 'motivo', 'usuario_id',
    ];

    public function producto()
    {
        return $this->belongsTo(ProductoCatalogo::class, 'producto_id');
    }

    public function ordenTrabajo()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
