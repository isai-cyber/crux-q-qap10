<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenTrabajoRepuesto extends Model
{
    public $timestamps = false;

    protected $table = 'orden_trabajo_repuesto';

    protected $fillable = ['orden_trabajo_id', 'producto_id', 'cantidad'];

    public function ordenTrabajo()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoCatalogo::class, 'producto_id');
    }
}
