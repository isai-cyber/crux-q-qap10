<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('endpoint', 120);
            $table->longText('entrada')->nullable();
            $table->longText('salida')->nullable();
            $table->string('modelo', 60)->nullable();
            $table->decimal('costo_estimado', 10, 4)->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_ia');
    }
};
