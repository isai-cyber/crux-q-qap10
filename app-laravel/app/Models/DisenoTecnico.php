<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class DisenoTecnico extends Model
{
    use BelongsToOrganizacion;

    public $timestamps = false;

    protected $table = 'disenos_tecnicos';

    protected $fillable = [
        'organizacion_id', 'tipo', 'titulo', 'descripcion_solicitud', 'contenido', 'formato',
        'activo_id', 'ticket_id', 'runbook_id', 'generado_por_ia', 'usuario_id',
    ];

    protected $casts = [
        'generado_por_ia' => 'boolean',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class);
    }
}
