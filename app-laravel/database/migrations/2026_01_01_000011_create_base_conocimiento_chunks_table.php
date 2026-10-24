<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_conocimiento_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('documento_origen', 150)->comment('Ej: BE2_es_es-ES.pdf');
            $table->string('capitulo', 150)->nullable();
            $table->longText('texto');
            $table->longText('embedding')->nullable()->comment('Vector JSON');
            $table->timestamp('created_at')->useCurrent();

            $table->index('documento_origen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_conocimiento_chunks');
    }
};
