<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'organizacion_id', 'nombre', 'email', 'password_hash', 'rol', 'planta_id', 'activo',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * La columna de contraseña se llama password_hash (heredado del esquema
     * original en PHP puro), no "password" como espera Laravel por defecto.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * NOTA IMPORTANTE: Usuario NO usa el trait BelongsToOrganizacion (a
     * diferencia de los demás modelos) porque es el propio modelo de
     * autenticación — un global scope que llama a auth()->user() dentro de
     * la resolución de auth()->user() provoca recursión infinita (bug real
     * encontrado en pruebas: "Maximum execution time... 184000+ frames").
     * En su lugar, se usa este scope local explícito, que los controladores
     * deben invocar a mano (Usuario::deMiOrganizacion()->...).
     */
    public function scopeDeMiOrganizacion(Builder $query): Builder
    {
        return $query->where('organizacion_id', auth()->user()->organizacion_id);
    }

    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class);
    }

    public function planta()
    {
        return $this->belongsTo(Planta::class);
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }
}
