<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disenos_tecnicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->enum('tipo', ['diagrama', 'documento']);
            $table->string('titulo', 200);
            $table->text('descripcion_solicitud');
            $table->longText('contenido')->comment('Markup SVG o texto Markdown según "formato"');
            $table->enum('formato', ['svg', 'markdown']);
            $table->foreignId('activo_id')->nullable()->constrained('activos')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('runbook_id')->nullable()->constrained('runbooks')->nullOnDelete();
            $table->boolean('generado_por_ia')->default(true);
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disenos_tecnicos');
    }
};
