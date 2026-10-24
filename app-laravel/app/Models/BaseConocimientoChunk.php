<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Base de conocimiento GLOBAL (manuales), compartida entre organizaciones. */
class BaseConocimientoChunk extends Model
{
    public $timestamps = false;

    protected $table = 'base_conocimiento_chunks';

    protected $fillable = ['documento_origen', 'capitulo', 'texto', 'embedding'];
}
