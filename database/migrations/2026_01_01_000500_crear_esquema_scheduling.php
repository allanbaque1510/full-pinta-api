<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Scheduling (§4.7): citas, items, auditoría, lista de espera,
 * proyección de disponibilidad e idempotencia.
 *
 * Aquí van los constraints críticos #2 y #3 del §4.11, que son lo que hace
 * correcto el agendamiento bajo concurrencia.
 */
return new class extends Migration
{
    /** Estados de la cita (§6). */
    private const ESTADOS = [
        'reservada', 'confirmada', 'en_curso', 'completada',
        'cancelada_cliente', 'cancelada_local', 'no_show', 'expirada', 'reagendada',
    ];

    /** Estados en los que la cita ocupa el slot de verdad. */
    private const ESTADOS_ACTIVOS = "'reservada','confirmada','en_curso'";

    public function up(): void
    {
        // `cita.cliente_nuevo` se calcula contra esta tabla: si no existe la
        // fila, es cliente nuevo para ese local.
        Schema::create('cliente_local', function (Blueprint $table) {
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->timestampTz('primera_cita_at')->nullable();
            $table->timestampTz('ultima_cita_at')->nullable();
            $table->integer('total_citas')->default(0);

            // Lo que hace que el cliente vuelva, y lo que un barbero suplente
            // necesita cuando el titular no está: "fade 2 a los lados, tijera
            // arriba", "tinte 7.3 + 20 vol". Es dato del local: el local A nunca
            // ve la nota del local B (§3.3).
            $table->text('nota')->nullable();

            $table->foreignUuid('profesional_preferido_id')->nullable()
                ->constrained('profesional')->nullOnDelete();

            $table->primary(['usuario_id', 'local_id']);
        });

        Schema::create('cita', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->restrictOnDelete();
            $table->foreignUuid('profesional_id')->constrained('profesional')->restrictOnDelete();
            $table->foreignUuid('recurso_id')->nullable()->constrained('recurso')->nullOnDelete();
            $table->foreignUuid('cliente_id')->constrained('usuario')->restrictOnDelete();

            $table->timestampTz('inicio');
            $table->timestampTz('fin');

            $table->string('estado', 32);

            // Los walk-ins tienen que entrar a la agenda (`canal = 'local'`). Si
            // el local atiende sin cita y no lo registra, la disponibilidad
            // miente y el cliente de la app llega a esperar. Eso mata la
            // confianza más rápido que cualquier bug.
            $table->string('canal', 16);

            $table->decimal('precio_total', 10, 2)->default(0);

            // Va 100% al profesional y NO es base de comisión. Si se suma al
            // total de la cita, se le cobra comisión al dueño sobre la propina
            // del barbero. Pelea garantizada.
            $table->decimal('propina', 10, 2)->default(0);

            $table->string('metodo_pago', 16)->nullable();
            $table->boolean('cliente_nuevo')->default(false);

            $table->string('para_tipo', 16)->default('titular');
            $table->string('para_nombre')->nullable();
            $table->foreignUuid('mascota_id')->nullable()->constrained('mascota')->nullOnDelete();

            $table->text('nota_cliente')->nullable();
            $table->string('codigo', 8)->unique();      // código corto para el local

            // Reagendar NO es cancelar + crear: eso le cuenta una cancelación al
            // cliente que sí avisó y pierde la trazabilidad.
            //
            // La FK a sí misma se añade más abajo: dentro del CREATE TABLE
            // todavía no existe la clave primaria a la que apuntar.
            $table->uuid('reagendada_de_id')->nullable();

            $table->timestampTz('expira_at')->nullable();   // hold de 10 min (§5.4)

            $table->timestampsTz();
            $table->timestampTz('confirmada_at')->nullable();
            $table->timestampTz('cancelada_at')->nullable();
            $table->timestampTz('completada_at')->nullable();
        });

        Schema::table('cita', function (Blueprint $table) {
            $table->foreign('reagendada_de_id')->references('id')->on('cita')->nullOnDelete();
        });

        Esquema::enum('cita', 'estado', self::ESTADOS);
        Esquema::enum('cita', 'canal', ['app', 'local', 'whatsapp']);
        Esquema::enum('cita', 'metodo_pago', ['efectivo', 'transferencia', 'tarjeta', 'payphone']);
        Esquema::enum('cita', 'para_tipo', ['titular', 'otra_persona', 'mascota']);
        Esquema::check('cita', 'fin', 'fin > inicio');
        Esquema::check('cita', 'propina', 'propina >= 0');
        Esquema::check('cita', 'precio_total', 'precio_total >= 0');
        Esquema::check('cita', 'mascota', "para_tipo <> 'mascota' OR mascota_id IS NOT NULL");

        Esquema::generada('cita', 'rango', 'tstzrange', "tstzrange(inicio, fin, '[)')");

        // §4.11 #2 — Un profesional no puede tener dos citas traslapadas.
        //
        // Es sobre `profesional_id` SOLO, no sobre (local_id, profesional_id).
        // Un profesional es un solo cuerpo y no puede estar en dos locales a la
        // vez. Scopear el constraint por local —que es el instinto natural—
        // introduce exactamente ese bug.
        Esquema::exclusion('cita', 'cita_profesional_sin_traslape', [
            'profesional_id' => '=',
            'rango' => '&&',
        ], 'estado IN ('.self::ESTADOS_ACTIVOS.')');

        // §4.11 #3 — Un recurso no puede estar ocupado dos veces. El recurso
        // pertenece a un local, así que esto ya es implícitamente por local.
        Esquema::exclusion('cita', 'cita_recurso_sin_traslape', [
            'recurso_id' => '=',
            'rango' => '&&',
        ], 'estado IN ('.self::ESTADOS_ACTIVOS.') AND recurso_id IS NOT NULL');

        DB::statement('CREATE INDEX cita_local_inicio ON cita (local_id, inicio)');
        DB::statement('CREATE INDEX cita_profesional_inicio ON cita (profesional_id, inicio)');
        DB::statement('CREATE INDEX cita_cliente_inicio ON cita (cliente_id, inicio DESC)');
        DB::statement("CREATE INDEX cita_holds_vencidos ON cita (expira_at) WHERE estado = 'reservada'");

        // Precio CONGELADO: si el local sube precios mañana, la cita agendada
        // ayer mantiene el precio pactado. Comisión CONGELADA: si el dueño le
        // cambia el porcentaje al barbero, las citas ya atendidas se liquidan
        // con el porcentaje vigente cuando se atendieron. Sin esto, cambiar una
        // comisión reescribe el pasado y se genera un reclamo.
        Schema::create('cita_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cita_id')->constrained('cita')->cascadeOnDelete();
            $table->foreignUuid('servicio_local_id')->constrained('servicio_local')->restrictOnDelete();
            $table->decimal('precio', 10, 2);
            $table->smallInteger('duracion_min');
            $table->boolean('comisionable')->default(true);
            $table->decimal('comision_pct', 5, 2)->default(0);

            $table->index('cita_id');
        });

        Esquema::check('cita_item', 'precio', 'precio >= 0');
        Esquema::check('cita_item', 'comision_pct', 'comision_pct BETWEEN 0 AND 100');

        Schema::create('cita_producto', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cita_id')->constrained('cita')->cascadeOnDelete();
            $table->foreignUuid('producto_id')->constrained('producto')->restrictOnDelete();
            $table->smallInteger('cantidad')->default(1);
            $table->decimal('precio', 10, 2);
            $table->decimal('comision_pct', 5, 2)->default(0);

            $table->index('cita_id');
        });

        Esquema::check('cita_producto', 'cantidad', 'cantidad > 0');
        Esquema::check('cita_producto', 'precio', 'precio >= 0');
        Esquema::check('cita_producto', 'comision_pct', 'comision_pct BETWEEN 0 AND 100');

        // Auditoría inmutable. Cuando el barbero diga "esa cita fue mía y no me
        // la pagaron", esto es la única respuesta posible. Para comisiones no es
        // opcional: es plata entre dos personas.
        Schema::create('cita_evento', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cita_id')->constrained('cita')->cascadeOnDelete();
            $table->string('estado_anterior', 32)->nullable();
            $table->string('estado_nuevo', 32);
            $table->uuid('actor_usuario_id')->nullable();
            $table->string('actor_rol', 32)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at');

            $table->index(['cita_id', 'created_at']);
        });

        // Cuesta poco, retiene mucho y convierte cancelaciones en citas. En
        // barbería los sábados se llenan; se usaría todo el tiempo.
        Schema::create('espera', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('cliente_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignUuid('profesional_id')->nullable()->constrained('profesional')->nullOnDelete();
            $table->foreignUuid('servicio_local_id')->constrained('servicio_local')->cascadeOnDelete();
            $table->date('fecha_deseada');
            $table->time('desde')->nullable();
            $table->time('hasta')->nullable();
            $table->string('estado', 20)->default('activa');
            $table->timestampsTz();

            $table->index(['local_id', 'fecha_deseada', 'estado']);
        });

        Esquema::enum('espera', 'estado', ['activa', 'notificada', 'convertida', 'expirada']);
        Esquema::check('espera', 'ventana', 'hasta IS NULL OR desde IS NULL OR hasta > desde');

        // Read model. El filtro "disponible hoy" no puede correr el motor de
        // slots por cada local del resultado: se cae. La búsqueda consulta esto,
        // que un job reconstruye por evento (§12.5).
        Schema::create('disponibilidad_dia', function (Blueprint $table) {
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->date('fecha');
            $table->smallInteger('slots_libres')->default(0);
            $table->time('primer_slot')->nullable();
            $table->time('ultimo_slot')->nullable();
            $table->timestampTz('recalculado_at');

            $table->primary(['local_id', 'fecha']);
            $table->index(['fecha', 'slots_libres']);
        });

        // En móvil la red se cae a mitad del POST, el usuario vuelve a tocar
        // "Agendar" y se crean dos citas. Pasa siempre (§12.4).
        Schema::create('idempotencia', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 64)->unique();
            $table->uuid('usuario_id')->nullable();
            $table->string('endpoint', 120);
            $table->smallInteger('status')->nullable();   // NULL = en curso
            $table->jsonb('respuesta')->nullable();
            $table->timestampTz('created_at');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotencia');
        Schema::dropIfExists('disponibilidad_dia');
        Schema::dropIfExists('espera');
        Schema::dropIfExists('cita_evento');
        Schema::dropIfExists('cita_producto');
        Schema::dropIfExists('cita_item');
        Schema::dropIfExists('cita');
        Schema::dropIfExists('cliente_local');
    }
};
