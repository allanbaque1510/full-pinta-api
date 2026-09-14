<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Directory (§4.4): negocio, local, horarios, amenidades y miembros.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabla de parámetros: referenciada por id desde `negocio` y desde
        // `suscripcion` (Billing) — antes era el mismo varchar+CHECK repetido
        // en las dos tablas.
        Schema::create('plan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 10)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
        });

        Schema::create('negocio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre_marca');
            $table->string('ruc', 13)->nullable();
            $table->foreignUuid('propietario_id')->constrained('usuario')->restrictOnDelete();
            $table->foreignUuid('plan_id')->constrained('plan')->restrictOnDelete();
            $table->date('plan_vigente_hasta')->nullable();
            $table->timestampsTz();

            $table->index('propietario_id');
        });

        Schema::create('local', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocio')->restrictOnDelete();
            $table->string('nombre');                       // "Sucursal Alborada"
            $table->text('direccion');
            $table->text('referencia')->nullable();          // "diagonal al parque"
            $table->geography('ubicacion', subtype: 'point', srid: 4326);
            $table->string('telefono', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->boolean('verificado')->default(false);
            $table->timestampTz('verificado_at')->nullable();
            $table->string('estado', 20);

            // Ventana de agendamiento del local.
            $table->smallInteger('lead_time_min')->default(60);      // anticipación mínima
            $table->smallInteger('horizonte_dias')->default(30);     // máximo a futuro
            $table->smallInteger('politica_cancelacion_horas')->default(2);

            // Lo calcula un job nocturno, nunca un request (§12.5).
            $table->decimal('score_ranking', 8, 4)->default(0);

            $table->timestampsTz();
        });

        Esquema::enum('local', 'estado', ['borrador', 'activo', 'pausado', 'suspendido']);

        // Sin índice espacial, "barberías cerca de mí" hace scan completo (§4.1).
        DB::statement('CREATE INDEX local_ubicacion_gist ON local USING gist (ubicacion)');
        DB::statement('CREATE INDEX local_estado_score ON local (estado, score_ranking)');

        // Varias filas por día permiten partir jornada (mañana/tarde).
        Schema::create('horario_local', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->smallInteger('dia_semana');   // 0 = domingo
            $table->time('abre');
            $table->time('cierra');

            $table->index(['local_id', 'dia_semana']);
        });

        Esquema::check('horario_local', 'dia_semana', 'dia_semana BETWEEN 0 AND 6');
        Esquema::check('horario_local', 'cierra', 'cierra > abre');

        // Tabla de parámetros: referenciada por id desde `amenidad`, en vez de
        // repetir el mismo varchar de categoría en cada fila.
        Schema::create('amenidad_categoria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->string('icono', 60)->nullable();
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        // "Acepta mascotas en sala" es una amenidad. "Baña perros" es un
        // servicio de la vertical mascotas. Confundirlas lleva clientes con su
        // perro a un local que solo lo deja entrar (§4.4).
        Schema::create('amenidad', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 60)->unique();
            $table->foreignUuid('categoria_id')->constrained('amenidad_categoria')->restrictOnDelete();
            $table->string('nombre');
            $table->string('icono', 60)->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('local_amenidad', function (Blueprint $table) {
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('amenidad_id')->constrained('amenidad')->cascadeOnDelete();
            $table->string('detalle')->nullable();   // "cerveza artesanal", "PS5"

            $table->primary(['local_id', 'amenidad_id']);
        });

        // Recepción es un rol aparte: agenda y cobra, pero no ve las comisiones
        // de los barberos (§3.2).
        Schema::create('negocio_miembro', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignUuid('negocio_id')->constrained('negocio')->cascadeOnDelete();
            $table->foreignUuid('local_id')->nullable()->constrained('local')->cascadeOnDelete();
            $table->string('rol', 20);
            $table->date('desde');
            $table->date('hasta')->nullable();   // NULL = vigente

            $table->index(['usuario_id', 'negocio_id']);
            $table->index(['negocio_id', 'rol']);
        });

        Esquema::enum('negocio_miembro', 'rol', ['propietario', 'admin', 'recepcion']);

        Schema::create('local_foto', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->string('url');
            $table->string('tipo', 20);
            $table->smallInteger('orden')->default(0);

            $table->index(['local_id', 'orden']);
        });

        Esquema::enum('local_foto', 'tipo', ['fachada', 'interior', 'trabajo']);
    }

    public function down(): void
    {
        Schema::dropIfExists('local_foto');
        Schema::dropIfExists('negocio_miembro');
        Schema::dropIfExists('local_amenidad');
        Schema::dropIfExists('amenidad');
        Schema::dropIfExists('amenidad_categoria');
        Schema::dropIfExists('horario_local');
        Schema::dropIfExists('local');
        Schema::dropIfExists('negocio');
        Schema::dropIfExists('plan');
    }
};
