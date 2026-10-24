<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->string('codigo', 60)->nullable();
            $table->enum('severidad', ['info', 'advertencia', 'critico'])->default('info');
            $table->text('descripcion');
            $table->enum('origen', ['manual', 'automatico'])->default('manual');
            $table->dateTime('fecha_diagnostico');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosticos');
    }
};
