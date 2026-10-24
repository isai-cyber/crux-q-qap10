<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_trabajo_repuesto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos_catalogo')->restrictOnDelete();
            $table->unsignedInteger('cantidad')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_trabajo_repuesto');
    }
};
