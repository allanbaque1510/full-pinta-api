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

            // Límites: NULL = ilimitado (§9.4).
            $table->smallInteger('limite_locales')->nullable();
            $table->smallInteger('limite_profesionales')->nullable();
            $table->smallInteger('limite_fotos')->nullable();

            // Capacidades por feature (§9.2, §9.4) — se leen directo de aquí,
            // nunca comparando `codigo === 'pro'` como proxy (revisión de base
            // de datos, 2026-09-28).
            $table->boolean('liquidacion_desglose')->default(false);
            $table->boolean('recordatorios_whatsapp')->default(false);
            $table->boolean('responder_resenas')->default(false);
            $table->boolean('estadisticas_completas')->default(false);
            $table->boolean('promociones_horas_valle')->default(false);
            $table->boolean('bloque_destacados')->default(false);

            // Precio (§9.6): $8 el primer profesional + $5 por cada adicional.
            $table->decimal('precio_base', 10, 2)->default(0);
            $table->decimal('precio_adicional', 10, 2)->default(0);
            $table->smallInteger('meses_pago_anual')->default(12);   // 10 = paga 10, se lleva 12

            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        Schema::create('negocio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre_marca');
            $table->string('ruc', 13)->nullable();
            $table->boolean('ruc_verificado')->default(false);
            $table->timestampTz('ruc_verificado_at')->nullable();
            $table->foreignUuid('propietario_id')->constrained('usuario')->restrictOnDelete();
            $table->foreignUuid('plan_id')->constrained('plan')->restrictOnDelete();
            $table->date('plan_vigente_hasta')->nullable();
            // Logo de marca y portada — propios del negocio, independientes de
            // la galería de fotos de cada `local` (fachada/interior/muestra).
            $table->foreignUuid('foto_perfil_id')->nullable()->constrained('imagen')->nullOnDelete();
            $table->foreignUuid('portada_imagen_id')->nullable()->constrained('imagen')->nullOnDelete();
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
        // servicio del rubro mascotas. Confundirlas lleva clientes con su
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

        // Extraído de la categoría 'pago' de `amenidad` (revisión de base de
        // datos, 2026-09-28): un método de pago no es una comodidad del local,
        // y `cita.metodo_pago` repetía el mismo enum. Tabla de parámetros +
        // pivote, mismo criterio que `amenidad`/`local_amenidad`.
        Schema::create('metodo_pago', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->string('icono', 60)->nullable();
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        Schema::create('local_metodo_pago', function (Blueprint $table) {
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('metodo_pago_id')->constrained('metodo_pago')->cascadeOnDelete();

            $table->primary(['local_id', 'metodo_pago_id']);
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

        // `local_foto` se reemplazó por la galería polimórfica `imagen`
        // (`objeto_type = 'local'`) — revisión de base de datos, 2026-09-28.
        // `local` se queda sin puntero: es galería pura (fachada/interior/
        // muestra), a diferencia de `usuario`/`profesional`/`mascota`, que sí
        // tienen `foto_perfil_id`.
    }

    public function down(): void
    {
        Schema::dropIfExists('negocio_miembro');
        Schema::dropIfExists('local_metodo_pago');
        Schema::dropIfExists('metodo_pago');
        Schema::dropIfExists('local_amenidad');
        Schema::dropIfExists('amenidad');
        Schema::dropIfExists('amenidad_categoria');
        Schema::dropIfExists('horario_local');
        Schema::dropIfExists('local');
        Schema::dropIfExists('negocio');
        Schema::dropIfExists('plan');
    }
};
