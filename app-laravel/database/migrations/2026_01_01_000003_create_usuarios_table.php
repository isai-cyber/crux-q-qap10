<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('email', 180);
            $table->string('password_hash', 255);
            $table->enum('rol', ['admin', 'supervisor', 'tecnico'])->default('tecnico');
            $table->foreignId('planta_id')->nullable()->constrained('plantas')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['organizacion_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
