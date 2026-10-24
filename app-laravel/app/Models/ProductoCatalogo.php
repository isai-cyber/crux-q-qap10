<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo GLOBAL (compartido entre organizaciones): no usa BelongsToOrganizacion.
 * El stock físico vive en Inventario, que sí es por organización.
 */
class ProductoCatalogo extends Model
{
    protected $table = 'productos_catalogo';

    protected $fillable = [
        'numero_parte', 'categoria_id', 'descripcion', 'especificaciones',
        'precio_lista', 'vigencia_catalogo', 'origen_documento', 'embedding',
    ];

    protected $casts = [
        'especificaciones' => 'array',
        'precio_lista' => 'decimal:2',
    ];

    public function categoria()
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id');
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class, 'producto_id');
    }

    /** Stock de la organización autenticada (0 si no tiene registro aún). */
    public function stockActual(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        return $this->inventarios()
            ->where('organizacion_id', auth()->user()->organizacion_id)
            ->value('stock_actual') ?? 0;
    }
}
