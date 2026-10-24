<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activo_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos_catalogo')->cascadeOnDelete();
            $table->unsignedInteger('cantidad')->default(1);

            $table->unique(['activo_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activo_producto');
    }
};
