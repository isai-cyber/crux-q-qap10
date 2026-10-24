<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('tipo', 100)->nullable();
            $table->foreignId('planta_id')->constrained('plantas')->restrictOnDelete();
            $table->string('ubicacion', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('nombre_profinet', 120)->nullable();
            $table->string('version_firmware', 60)->nullable();
            $table->date('fecha_puesta_marcha')->nullable();
            $table->enum('estado', ['operativo', 'en_mantenimiento', 'fuera_servicio'])->default('operativo');
            $table->string('foto_path', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activos');
    }
};
