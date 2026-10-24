<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('folio', 20);
            $table->foreignId('activo_id')->nullable()->constrained('activos')->nullOnDelete();
            $table->foreignId('reportado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->enum('canal_origen', ['web', 'chat', 'whatsapp', 'slack', 'email', 'api'])->default('web');
            $table->text('descripcion');
            $table->string('categoria_ia', 60)->nullable();
            $table->string('categoria_final', 60)->nullable();
            $table->enum('prioridad_ia', ['baja', 'media', 'alta', 'critica'])->nullable();
            $table->enum('prioridad_final', ['baja', 'media', 'alta', 'critica'])->nullable();
            $table->enum('estado', ['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'])
                ->default('abierto');
            $table->dateTime('fecha_apertura')->useCurrent();
            $table->dateTime('fecha_cierre')->nullable();
            $table->timestamps();

            $table->unique(['organizacion_id', 'folio']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
