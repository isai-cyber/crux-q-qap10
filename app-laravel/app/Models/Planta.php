<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class Planta extends Model
{
    use BelongsToOrganizacion;

    protected $fillable = ['organizacion_id', 'nombre', 'ubicacion'];

    public function activos()
    {
        return $this->hasMany(Activo::class);
    }
}
