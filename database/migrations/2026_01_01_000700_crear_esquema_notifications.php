<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Notifications (§4.9): tokens de dispositivo, preferencias, log de
 * envíos y plantillas de WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabla de parámetros: referenciada por id desde `preferencia_notificacion`
        // y `notificacion` — antes era el mismo varchar+CHECK repetido en las
        // dos tablas del mismo módulo.
        Schema::create('notificacion_categoria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
        });

        Schema::create('device_token', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('token');
            $table->string('plataforma', 10);
            $table->string('apns_token')->nullable();   // iOS: el token APNs que FCM necesita
            $table->string('app_version')->nullable();

            // FCM devuelve UNREGISTERED o INVALID_ARGUMENT: marcar activo=false.
            // Si no, la tasa de entrega miente (§11.9).
            $table->boolean('activo')->default(true);

            $table->timestampTz('ultimo_uso_at')->nullable();
            $table->timestampsTz();

            $table->unique('token');
            $table->index(['usuario_id', 'activo']);
        });

        Esquema::enum('device_token', 'plataforma', ['android', 'ios']);

        // Se respeta siempre, SALVO para lo transaccional indispensable y el
        // OTP. Nadie se desuscribe de que le avisen que su cita se canceló.
        Schema::create('preferencia_notificacion', function (Blueprint $table) {
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignUuid('categoria_id')->constrained('notificacion_categoria')->restrictOnDelete();
            $table->boolean('push')->default(true);
            $table->boolean('whatsapp')->default(true);

            $table->primary(['usuario_id', 'categoria_id']);
        });

        Schema::create('notificacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('tipo_evento');
            $table->foreignUuid('categoria_id')->constrained('notificacion_categoria')->restrictOnDelete();
            $table->string('canal', 20);
            $table->foreignUuid('cita_id')->nullable()->constrained('cita')->cascadeOnDelete();
            $table->string('plantilla')->nullable();
            $table->string('estado', 20)->default('programada');
            $table->timestampTz('programada_para');
            $table->timestampTz('enviada_at')->nullable();
            $table->string('proveedor_id')->nullable();   // id del mensaje en FCM o Meta

            // Para que tres actualizaciones de la misma cita no apilen tres
            // notificaciones (§11.9).
            $table->string('collapse_id')->nullable();

            // Por fila, para saber el costo real por local (§11.3).
            $table->decimal('costo_usd', 8, 5)->default(0);

            $table->text('error')->nullable();
            $table->timestampsTz();

            // El seguro contra duplicados por reintentos de la cola.
            $table->unique(['cita_id', 'tipo_evento', 'canal']);

            $table->index(['estado', 'programada_para']);
            $table->index(['usuario_id', 'created_at']);
        });

        Esquema::enum('notificacion', 'canal', ['push', 'whatsapp', 'sms', 'websocket']);
        Esquema::enum('notificacion', 'estado', [
            'programada', 'enviada', 'entregada', 'leida', 'fallida', 'cancelada',
        ]);
        Esquema::check('notificacion', 'costo_usd', 'costo_usd >= 0');

        // Las aprueba Meta, no nosotros. El trámite toma días y el texto no se
        // puede cambiar sin volver a aprobar: bloquea el lanzamiento si se deja
        // al final (§11.9).
        Schema::create('plantilla_whatsapp', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre')->unique();
            $table->string('categoria', 20);
            $table->string('idioma', 10)->default('es');
            $table->string('estado', 20)->default('pendiente');
            $table->jsonb('variables')->nullable();
            $table->timestampsTz();
        });

        Esquema::enum('plantilla_whatsapp', 'categoria', ['utility', 'authentication', 'marketing']);
        Esquema::enum('plantilla_whatsapp', 'estado', ['pendiente', 'aprobada', 'rechazada', 'pausada']);
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_whatsapp');
        Schema::dropIfExists('notificacion');
        Schema::dropIfExists('preferencia_notificacion');
        Schema::dropIfExists('notificacion_categoria');
        Schema::dropIfExists('device_token');
    }
};
