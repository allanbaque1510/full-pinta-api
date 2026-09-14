<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Staffing (§4.6): profesionales, asignaciones, turnos, recursos,
 * habilidades y excepciones.
 *
 * Aquí va el primero de los tres constraints críticos del §4.11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesional', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable a propósito: muchos barberos no van a instalar nada y el
            // local gestiona su agenda. El perfil existe igual.
            $table->foreignUuid('usuario_id')->nullable()->unique()
                ->constrained('usuario')->nullOnDelete();

            $table->string('nombre');
            $table->string('alias')->nullable();        // "Kevin el Fade"
            $table->text('bio')->nullable();
            $table->string('foto_url')->nullable();
            $table->boolean('independiente')->default(false);   // renta silla vs empleado
            $table->boolean('perfil_publico')->default(true);

            // Minutos mínimos de separación cuando dos citas consecutivas son en
            // locales distintos. NO lo puede validar un constraint: la base no
            // sabe de geografía. Va en el motor de disponibilidad (§5.1).
            $table->smallInteger('traslado_min')->default(30);

            $table->timestampsTz();
        });

        // El portafolio importa más de lo que parece: la gente escoge barbero
        // viendo cortes, no leyendo precios.
        Schema::create('profesional_foto', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profesional_id')->constrained('profesional')->cascadeOnDelete();
            $table->string('url');
            $table->smallInteger('orden')->default(0);

            $table->index(['profesional_id', 'orden']);
        });

        // El vínculo laboral, SIN horarios. Un profesional puede tener varias
        // asignaciones vigentes a la vez (día en un local, noche en otro).
        Schema::create('asignacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('profesional_id')->constrained('profesional')->cascadeOnDelete();
            $table->string('rol', 20);
            $table->string('modalidad', 20);
            $table->decimal('comision_pct', 5, 2);
            $table->date('desde');
            $table->date('hasta')->nullable();   // NULL = vigente
            $table->timestampsTz();

            $table->index(['local_id', 'profesional_id']);
        });

        Esquema::enum('asignacion', 'rol', ['barbero', 'estilista', 'manicurista', 'groomer', 'recepcion']);
        Esquema::enum('asignacion', 'modalidad', ['empleado', 'renta_silla', 'invitado']);
        Esquema::check('asignacion', 'comision_pct', 'comision_pct BETWEEN 0 AND 100');

        Schema::create('turno', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asignacion_id')->constrained('asignacion')->cascadeOnDelete();

            // Desnormalizados: existen SOLO para habilitar el constraint de
            // exclusión de abajo. Se escriben en la misma transacción que la
            // asignación.
            $table->foreignUuid('profesional_id')->constrained('profesional')->cascadeOnDelete();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();

            $table->smallInteger('dia_semana');
            $table->time('entra');
            $table->time('sale');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestampsTz();

            $table->index(['local_id', 'dia_semana'], 'turno_local_dia');
        });

        Esquema::check('turno', 'dia_semana', 'dia_semana BETWEEN 0 AND 6');

        // Los turnos que cruzan medianoche se parten en dos filas
        // (mié 20:00–23:59 + jue 00:00–01:00). Ver §4.6.
        Esquema::check('turno', 'sale', 'sale > entra');

        Esquema::generada('turno', 'rango', 'franja', "franja(entra, sale, '[)')");
        Esquema::generada('turno', 'vigencia', 'daterange', "daterange(vigente_desde, vigente_hasta, '[)')");

        // §4.11 #1 — Los turnos de un profesional no se traslapan, aunque sean
        // de locales distintos. Sin esto, un local crea un turno que pisa el de
        // otro y el conflicto se descubre recién al agendar, con el cliente
        // esperando.
        //
        // `vigencia` es indispensable: sin ella, cuando el barbero cambia de
        // horario a futuro el turno nuevo choca con el actual y Postgres lo
        // rechaza aunque sea legítimo.
        Esquema::exclusion('turno', 'turno_sin_traslape', [
            'profesional_id' => '=',
            'dia_semana' => '=',
            'rango' => '&&',
            'vigencia' => '&&',
        ]);

        // Overrides por fecha concreta ("este sábado no voy a Urdesa, voy a
        // Alborada"). No se modifica el turno recurrente: el cálculo de
        // disponibilidad aplica primero los recurrentes y luego estos.
        Schema::create('turno_fecha', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profesional_id')->constrained('profesional')->cascadeOnDelete();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('entra')->nullable();
            $table->time('sale')->nullable();
            $table->string('tipo', 20);
            $table->text('nota')->nullable();
            $table->timestampsTz();

            $table->index(['profesional_id', 'fecha']);
        });

        Esquema::enum('turno_fecha', 'tipo', ['extra', 'reemplaza', 'cancela']);
        Esquema::check('turno_fecha', 'horas', <<<'SQL'
            (tipo = 'cancela' AND entra IS NULL AND sale IS NULL)
            OR (tipo <> 'cancela' AND entra IS NOT NULL AND sale IS NOT NULL AND sale > entra)
        SQL);

        // UNA FILA POR UNIDAD FÍSICA, nunca una fila con `cantidad = 3`. Con un
        // campo cantidad el constraint de exclusión de citas subvende (permite
        // una sola a la vez) o, si se relaja, sobrevende. Además permite marcar
        // una mesa fuera de servicio sin afectar las otras.
        Schema::create('recurso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('tipo_recurso_id')->constrained('tipo_recurso')->restrictOnDelete();
            $table->string('nombre', 60);      // "Silla 3", "Mesa 1"
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->index(['local_id', 'tipo_recurso_id', 'activo']);
        });

        // La tabla que todos olvidan y la que rompe el agendamiento. No todos
        // los barberos hacen todo: si el cliente agenda uñas y el sistema le
        // asigna al barbero que solo hace fades, hay problema el día uno.
        Schema::create('habilidad', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profesional_id')->constrained('profesional')->cascadeOnDelete();
            $table->foreignUuid('servicio_local_id')->constrained('servicio_local')->cascadeOnDelete();
            $table->decimal('precio_override', 10, 2)->nullable();   // senior cobra más

            $table->unique(['profesional_id', 'servicio_local_id']);
            $table->index('servicio_local_id', 'habilidad_servicio');
        });

        Esquema::check('habilidad', 'precio_override', 'precio_override IS NULL OR precio_override >= 0');

        // Una ausencia del profesional (`profesional_id` con `local_id` NULL) lo
        // bloquea en TODOS sus locales: no está enfermo solo en una sucursal.
        Schema::create('excepcion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->nullable()->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('profesional_id')->nullable()->constrained('profesional')->cascadeOnDelete();
            $table->foreignUuid('recurso_id')->nullable()->constrained('recurso')->cascadeOnDelete();
            $table->timestampTz('fecha_inicio');
            $table->timestampTz('fecha_fin');
            $table->string('motivo', 20);
            $table->text('nota')->nullable();
            $table->timestampsTz();

            $table->index(['local_id', 'fecha_inicio', 'fecha_fin']);
        });

        Esquema::enum('excepcion', 'motivo', [
            'feriado', 'vacaciones', 'mantenimiento', 'personal', 'bloqueo_manual',
        ]);
        Esquema::check('excepcion', 'fechas', 'fecha_fin > fecha_inicio');
        Esquema::check('excepcion', 'objetivo',
            'local_id IS NOT NULL OR profesional_id IS NOT NULL OR recurso_id IS NOT NULL');

        DB::statement('CREATE INDEX excepcion_prof_rango ON excepcion (profesional_id, fecha_inicio, fecha_fin)');
    }

    public function down(): void
    {
        Schema::dropIfExists('excepcion');
        Schema::dropIfExists('habilidad');
        Schema::dropIfExists('recurso');
        Schema::dropIfExists('turno_fecha');
        Schema::dropIfExists('turno');
        Schema::dropIfExists('asignacion');
        Schema::dropIfExists('profesional_foto');
        Schema::dropIfExists('profesional');
    }
};
