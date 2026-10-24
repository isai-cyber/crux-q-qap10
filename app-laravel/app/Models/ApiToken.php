<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    use BelongsToOrganizacion;

    public $timestamps = false;

    protected $fillable = ['organizacion_id', 'nombre', 'token_hash', 'permisos', 'usuario_creador_id', 'activo'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'permisos' => 'array',
        'activo' => 'boolean',
        'ultimo_uso' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, $this->permisos ?? [], true);
    }
}
