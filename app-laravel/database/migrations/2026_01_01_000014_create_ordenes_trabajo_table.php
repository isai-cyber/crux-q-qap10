<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('runbook_id')->nullable()->constrained('runbooks')->nullOnDelete()
                ->comment('Runbook aplicado para resolver, si se usó uno');
            $table->text('diagnostico')->nullable();
            $table->text('solucion_aplicada')->nullable();
            $table->boolean('solucion_exitosa')->nullable()
                ->comment('Retroalimentación: ¿la solución funcionó? Alimenta la autoevolución de runbooks');
            $table->unsignedInteger('tiempo_invertido_minutos')->nullable();
            $table->enum('estado', ['pendiente', 'en_progreso', 'completada'])->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_trabajo');
    }
};
