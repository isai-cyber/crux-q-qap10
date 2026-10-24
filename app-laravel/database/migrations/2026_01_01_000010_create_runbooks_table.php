<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('titulo', 200);
            $table->text('sintoma');
            $table->text('causa')->nullable();
            $table->text('solucion');
            $table->foreignId('activo_id')->nullable()->constrained('activos')->nullOnDelete();
            $table->enum('generado_por', ['ia', 'tecnico'])->default('tecnico');
            $table->boolean('validado')->default(false);
            $table->boolean('vigente')->default(true)
                ->comment('Se pone en 0 automáticamente si su tasa de fallo es alta');
            $table->unsignedInteger('veces_aplicado')->default(0);
            $table->unsignedInteger('veces_exitoso')->default(0);
            $table->unsignedInteger('veces_fallido')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runbooks');
    }
};
