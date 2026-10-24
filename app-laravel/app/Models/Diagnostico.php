<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Diagnostico extends Model
{
    public $timestamps = false;

    protected $fillable = ['activo_id', 'codigo', 'severidad', 'descripcion', 'origen', 'fecha_diagnostico'];

    protected $casts = [
        'fecha_diagnostico' => 'datetime',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class);
    }
}
