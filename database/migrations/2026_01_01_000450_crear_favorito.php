<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `favorito` (§4.3) es del módulo Identity, pero apunta a `local` (Directory) y
 * `profesional` (Staffing), así que se crea después de ellos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorito', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignUuid('local_id')->nullable()->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('profesional_id')->nullable()->constrained('profesional')->cascadeOnDelete();
            $table->timestampsTz();

            // Un favorito es de un local o de un profesional, no de ambos.
            $table->unique(['usuario_id', 'local_id']);
            $table->unique(['usuario_id', 'profesional_id']);
        });

        Esquema::check('favorito', 'objetivo',
            '(local_id IS NOT NULL) <> (profesional_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('favorito');
    }
};
