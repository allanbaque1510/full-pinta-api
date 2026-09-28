<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galería polimórfica compartida (§4.4, revisión de base de datos, 2026-09-28):
 * reemplaza `local_foto`/`profesional_foto` (antes una tabla por dueño, con el
 * mismo shape repetido) y los `usuario.foto_url`/`profesional.foto_url`
 * sueltos. Corre antes que `Identity` (000100) porque `usuario.foto_perfil_id`
 * ya necesita esta tabla para su FK.
 *
 * `objeto_type` viaja por `Relation::morphMap()` (ver `AppServiceProvider`),
 * nunca el FQCN — convención pura de Laravel (`uuidMorphs`), sin `CHECK`: el
 * conjunto de dueños crece (`negocio`, `producto` ya están anticipados en el
 * plan) y no vale la pena alterar un constraint cada vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_imagen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();   // perfil, portada, fachada, interior, muestra
            $table->string('nombre');
            $table->string('icono', 60)->nullable();
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        Schema::create('imagen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('objeto_type', 20);
            $table->uuid('objeto_id');
            $table->foreignUuid('tipo_id')->constrained('tipo_imagen')->restrictOnDelete();
            $table->string('url');
            $table->smallInteger('orden')->default(0);
            $table->timestampTz('created_at');

            $table->index(['objeto_type', 'objeto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagen');
        Schema::dropIfExists('tipo_imagen');
    }
};
