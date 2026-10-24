<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaProducto extends Model
{
    public $timestamps = false;

    protected $table = 'categorias_producto';

    protected $fillable = ['nombre', 'categoria_padre_id', 'descripcion'];

    public function productos()
    {
        return $this->hasMany(ProductoCatalogo::class, 'categoria_id');
    }
}
