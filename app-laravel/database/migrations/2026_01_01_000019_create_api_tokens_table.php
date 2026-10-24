<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('token_hash', 255)->unique();
            $table->json('permisos');
            $table->foreignId('usuario_creador_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->dateTime('ultimo_uso')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
