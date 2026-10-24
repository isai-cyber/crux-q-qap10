<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos_catalogo')->cascadeOnDelete();
            $table->integer('stock_actual')->default(0);

            $table->unique(['organizacion_id', 'producto_id']);
        });

        DB::statement('ALTER TABLE inventario ADD CONSTRAINT chk_inventario_no_negativo CHECK (stock_actual >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};
