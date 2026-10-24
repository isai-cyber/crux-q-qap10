<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

/**
 * Conexión SALIENTE a un servidor MCP de un agente externo (contraparte
 * cliente del servidor MCP propio en /mcp). Pantalla "Integraciones MCP".
 */
class ConexionMcp extends Model
{
    use BelongsToOrganizacion;

    protected $table = 'conexiones_mcp';

    protected $fillable = [
        'organizacion_id', 'nombre', 'url', 'token_cifrado', 'activo',
        'ultima_verificacion', 'ultimo_estado', 'ultimo_error',
    ];

    protected $hidden = ['token_cifrado'];

    protected $casts = [
        'activo' => 'boolean',
        'ultima_verificacion' => 'datetime',
    ];

    protected function token(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->token_cifrado ? decrypt($this->token_cifrado) : null,
            set: fn (?string $value) => ['token_cifrado' => $value ? encrypt($value) : null],
        );
    }
}
