<?php

namespace App\Models\Concerns;

use App\Models\Organizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla automáticamente cada consulta y creación por la organización del
 * usuario autenticado (global scope), en vez de tener que pasar $organizacionId
 * a mano en cada método como en la versión PHP pura anterior. Reduce el
 * riesgo de fuga de datos entre organizaciones si se olvida el filtro en un
 * método nuevo.
 */
trait BelongsToOrganizacion
{
    protected static function bootBelongsToOrganizacion(): void
    {
        static::addGlobalScope('organizacion', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where(
                    $builder->getModel()->getTable().'.organizacion_id',
                    auth()->user()->organizacion_id
                );
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->organizacion_id) && auth()->check()) {
                $model->organizacion_id = auth()->user()->organizacion_id;
            }
        });
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class);
    }
}
