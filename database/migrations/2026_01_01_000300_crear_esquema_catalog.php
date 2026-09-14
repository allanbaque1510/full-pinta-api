<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Catalog (§4.5): catálogo maestro, servicios del local y productos.
 *
 * El catálogo lo define la plataforma, no los locales. Si cada local escribe
 * sus servicios en texto libre, se termina con "corte caballero", "corte de
 * cabello", "fade" y "CORTE" como cosas distintas, y la búsqueda y el filtro de
 * precios mueren. No hay vuelta atrás fácil de eso.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabla de parámetros: barbería, estética, uñas, mascotas. Referenciada
        // por id desde `servicio_categoria`, `catalogo_servicio` y
        // `solicitud_catalogo` — nunca repetida como varchar suelto en cada una.
        Schema::create('vertical', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
        });

        // El mismo código existe en verticales distintas — `corte` es categoría
        // de barbería y también de estética, y no son la misma cosa — por eso
        // `codigo` es único solo junto a `vertical_id`, no por sí mismo.
        Schema::create('servicio_categoria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vertical_id')->constrained('vertical')->restrictOnDelete();
            $table->string('codigo', 40);
            $table->string('nombre');
            $table->string('icono', 60)->nullable();
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->unique(['vertical_id', 'codigo']);
        });

        // Tabla de parámetros: referenciada por id desde `catalogo_servicio`
        // (qué tipo de recurso necesita el servicio) y desde `recurso`
        // (Staffing: qué tipo de recurso ES esa unidad física). Antes eran dos
        // varchar+CHECK sueltos con casi el mismo vocabulario y sin relación
        // entre sí — nada garantizaba que el agendamiento pudiera casar un
        // servicio de tipo "silla" contra un recurso físico del mismo tipo.
        // `ninguno` solo aplica a servicios (un recurso físico siempre es algo
        // concreto), así que `recurso.tipo_recurso_id` nunca apunta a esa fila.
        Schema::create('tipo_recurso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
        });

        Schema::create('catalogo_servicio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Sin `vertical_id` propio: se deriva de `categoria_id` ->
            // `servicio_categoria.vertical_id`. Guardarlo aparte duplicaría el
            // dato y podría desincronizarse (un servicio con categoría de
            // barbería pero vertical "estetica", por ejemplo).
            $table->foreignUuid('categoria_id')->constrained('servicio_categoria')->restrictOnDelete();
            $table->string('nombre');                 // "Corte fade"
            $table->string('slug', 120)->unique();
            $table->smallInteger('duracion_base_min');
            $table->foreignUuid('tipo_recurso_id')->constrained('tipo_recurso')->restrictOnDelete();
            $table->boolean('activo')->default(true);

            $table->index(['categoria_id', 'activo']);
        });

        Schema::create('servicio_local', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('catalogo_servicio_id')->constrained('catalogo_servicio')->restrictOnDelete();
            $table->decimal('precio', 10, 2);
            $table->boolean('precio_desde')->default(false);   // true => "desde $X"
            $table->smallInteger('duracion_min');
            $table->smallInteger('buffer_min')->default(0);    // limpieza posterior
            $table->boolean('comisionable')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->unique(['local_id', 'catalogo_servicio_id']);
            $table->index(['local_id', 'activo'], 'servicio_local_activo');
        });

        Esquema::check('servicio_local', 'precio', 'precio >= 0');
        Esquema::check('servicio_local', 'duracion_min', 'duracion_min > 0');
        Esquema::check('servicio_local', 'buffer_min', 'buffer_min >= 0');

        // Obligatorio para grooming: el precio y la duración dependen del
        // animal. Bañar un yorkshire no cuesta lo mismo que un golden.
        Schema::create('servicio_local_tamano', function (Blueprint $table) {
            $table->foreignUuid('servicio_local_id')->constrained('servicio_local')->cascadeOnDelete();
            $table->foreignUuid('tamano_id')->constrained('tamano_mascota')->restrictOnDelete();
            $table->decimal('precio', 10, 2);
            $table->smallInteger('duracion_min');

            $table->primary(['servicio_local_id', 'tamano_id']);
        });

        Esquema::check('servicio_local_tamano', 'precio', 'precio >= 0');
        Esquema::check('servicio_local_tamano', 'duracion_min', 'duracion_min > 0');

        // Cómo un local pide que la plataforma agregue un servicio que falta,
        // en vez de escribirlo en texto libre.
        Schema::create('solicitud_catalogo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('vertical_id')->constrained('vertical')->restrictOnDelete();
            $table->string('nombre_propuesto');
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->foreignUuid('catalogo_servicio_id')->nullable()
                ->constrained('catalogo_servicio')->nullOnDelete();   // se llena al aprobar
            $table->text('motivo_rechazo')->nullable();
            $table->timestampsTz();

            $table->index('estado');
        });

        Esquema::enum('solicitud_catalogo', 'estado', ['pendiente', 'aprobada', 'rechazada']);

        // Necesarios para que la liquidación de comisiones sea correcta: llevan
        // porcentaje distinto (o cero) al de un servicio.
        Schema::create('producto', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->string('nombre');              // pomada, cera, shampoo
            $table->decimal('precio', 10, 2);
            $table->decimal('comision_pct', 5, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->index(['local_id', 'activo']);
        });

        Esquema::check('producto', 'precio', 'precio >= 0');
        Esquema::check('producto', 'comision_pct', 'comision_pct BETWEEN 0 AND 100');
    }

    public function down(): void
    {
        Schema::dropIfExists('producto');
        Schema::dropIfExists('solicitud_catalogo');
        Schema::dropIfExists('servicio_local_tamano');
        Schema::dropIfExists('servicio_local');
        Schema::dropIfExists('catalogo_servicio');
        Schema::dropIfExists('tipo_recurso');
        Schema::dropIfExists('servicio_categoria');
        Schema::dropIfExists('vertical');
    }
};
