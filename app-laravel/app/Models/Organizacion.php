<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organizacion extends Model
{
    public $timestamps = false;

    protected $table = 'organizaciones';

    protected $fillable = ['nombre', 'slug', 'plan', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class);
    }

    public function plantas()
    {
        return $this->hasMany(Planta::class);
    }
}
