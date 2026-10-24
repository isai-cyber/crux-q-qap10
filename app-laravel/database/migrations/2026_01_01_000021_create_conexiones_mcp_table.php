<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conexiones SALIENTES a servidores MCP de agentes externos (contraparte
     * cliente del servidor MCP propio en /mcp). Ver Fase 6 del plan de
     * migración: pantalla "Integraciones MCP" para agregar agentes
     * plug-and-play sin editar código.
     */
    public function up(): void
    {
        Schema::create('conexiones_mcp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('url', 500);
            $table->text('token_cifrado')->nullable();
            $table->boolean('activo')->default(true);
            $table->dateTime('ultima_verificacion')->nullable();
            $table->string('ultimo_estado', 30)->nullable()->comment('conectado | error');
            $table->text('ultimo_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conexiones_mcp');
    }
};
