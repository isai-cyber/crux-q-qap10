<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->string('numero_parte', 80)->unique();
            $table->foreignId('categoria_id')->nullable()
                ->constrained('categorias_producto')->nullOnDelete();
            $table->string('descripcion', 500);
            $table->json('especificaciones')->nullable();
            $table->decimal('precio_lista', 12, 2)->nullable();
            $table->string('vigencia_catalogo', 20)->nullable()->comment('Ej: enero 2026');
            $table->string('origen_documento', 120)->nullable()->default('ic21-cppc-06000-0921');
            $table->longText('embedding')->nullable()->comment('Vector JSON para búsqueda semántica');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_catalogo');
    }
};
