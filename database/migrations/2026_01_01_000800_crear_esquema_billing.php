<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Billing (§4.10): suscripción del negocio, cobros y liquidación de
 * comisiones.
 *
 * La liquidación está fuera de la v1 (§15.2) — es el gancho del plan Pro — pero
 * el esquema va desde ya porque sale casi gratis de este modelo y es la red de
 * seguridad del producto (§17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripcion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocio')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('plan')->restrictOnDelete();
            $table->smallInteger('profesionales')->default(0);   // base del precio
            $table->decimal('precio_mensual', 10, 2)->default(0);
            $table->string('ciclo', 10)->default('mensual');
            $table->string('estado', 20)->default('activa');
            $table->date('vigente_hasta')->nullable();
            $table->timestampsTz();

            $table->index(['negocio_id', 'estado']);
        });

        Esquema::enum('suscripcion', 'ciclo', ['mensual', 'anual']);
        Esquema::enum('suscripcion', 'estado', ['activa', 'gracia', 'vencida', 'cancelada']);
        Esquema::check('suscripcion', 'precio_mensual', 'precio_mensual >= 0');
        Esquema::check('suscripcion', 'profesionales', 'profesionales >= 0');

        Schema::create('cobro', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('suscripcion_id')->constrained('suscripcion')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('estado', 20)->default('pendiente');
            $table->smallInteger('intentos')->default(0);
            $table->timestampTz('pagado_at')->nullable();
            $table->string('comprobante_sri')->nullable();   // clave de acceso de la factura electrónica
            $table->timestampsTz();

            $table->index(['suscripcion_id', 'estado']);
        });

        Esquema::enum('cobro', 'estado', ['pendiente', 'pagado', 'fallido', 'reembolsado']);
        Esquema::check('cobro', 'monto', 'monto >= 0');

        // `total_propinas` se suma al pago del profesional pero NO entra en la
        // base de comisión (§4.10).
        Schema::create('liquidacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('profesional_id')->constrained('profesional')->cascadeOnDelete();
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->decimal('total_servicios', 10, 2)->default(0);
            $table->decimal('total_productos', 10, 2)->default(0);
            $table->decimal('total_propinas', 10, 2)->default(0);
            $table->decimal('comision_servicios', 10, 2)->default(0);
            $table->decimal('comision_productos', 10, 2)->default(0);
            $table->decimal('total_a_pagar', 10, 2)->default(0);
            $table->string('estado', 20)->default('borrador');
            $table->timestampTz('cerrada_at')->nullable();
            $table->timestampsTz();

            $table->unique(['local_id', 'profesional_id', 'periodo_desde', 'periodo_hasta']);
        });

        Esquema::enum('liquidacion', 'estado', ['borrador', 'cerrada', 'pagada']);
        Esquema::check('liquidacion', 'periodo', 'periodo_hasta >= periodo_desde');
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion');
        Schema::dropIfExists('cobro');
        Schema::dropIfExists('suscripcion');
    }
};
