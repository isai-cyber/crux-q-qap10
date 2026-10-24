<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use BelongsToOrganizacion;

    protected $fillable = [
        'organizacion_id', 'folio', 'activo_id', 'reportado_por_id', 'canal_origen',
        'descripcion', 'categoria_ia', 'categoria_final', 'prioridad_ia', 'prioridad_final',
        'estado', 'fecha_apertura', 'fecha_cierre',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class);
    }

    public function reportadoPor()
    {
        return $this->belongsTo(Usuario::class, 'reportado_por_id');
    }

    public function ordenTrabajo()
    {
        return $this->hasOne(OrdenTrabajo::class);
    }

    /**
     * Folio secuencial POR ORGANIZACIÓN (TCK-2026-0001...). La restricción
     * única es (organizacion_id, folio) — nunca folio solo, porque dos
     * organizaciones distintas numeran su propia serie desde 1 (bug real
     * encontrado y corregido en la versión PHP pura: una restricción única
     * global chocaba entre organizaciones).
     */
    public static function generarFolio(int $organizacionId): string
    {
        $anio = now()->format('Y');
        $conteo = static::withoutGlobalScopes()
            ->where('organizacion_id', $organizacionId)
            ->where('folio', 'like', "TCK-{$anio}-%")
            ->count() + 1;

        return sprintf('TCK-%s-%04d', $anio, $conteo);
    }
}
