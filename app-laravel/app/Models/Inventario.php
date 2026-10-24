<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    use BelongsToOrganizacion;

    protected $table = 'inventario';

    public $timestamps = false;

    protected $fillable = ['organizacion_id', 'producto_id', 'stock_actual'];

    public function producto()
    {
        return $this->belongsTo(ProductoCatalogo::class, 'producto_id');
    }
}
