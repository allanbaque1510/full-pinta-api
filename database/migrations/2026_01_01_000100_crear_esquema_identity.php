<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Identity (§4.3): usuarios, perfil de cliente, mascotas y
 * consentimientos.
 *
 * `favorito` vive en este módulo pero se crea más tarde (§Staffing), porque
 * apunta a `local` y `profesional`, que todavía no existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        // En Ecuador el teléfono es mejor identificador que el email: más gente
        // lo tiene consistente y sirve para WhatsApp. Verificación por OTP.
        Schema::create('usuario', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('telefono', 20)->unique();
            $table->boolean('telefono_verificado')->default(false);
            $table->string('email')->nullable()->unique();
            $table->string('nombre');
            $table->string('foto_url')->nullable();

            // NULL = cliente sombra: la recepción lo creó con nombre y teléfono
            // para un walk-in. Cuando esa persona se registre con el mismo
            // teléfono, reclama el registro por OTP y hereda su historial.
            $table->string('password_hash')->nullable();

            // LOPDP: derecho de eliminación (§13.1). No se borra la fila, se
            // anonimiza, porque las citas históricas deben seguir cuadrando.
            $table->timestampTz('anonimizado_at')->nullable();

            $table->rememberToken();
            $table->timestampsTz();
        });

        Schema::create('cliente_perfil', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->unique()->constrained('usuario')->cascadeOnDelete();

            $table->string('genero', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();

            // Confiabilidad del cliente: se registra pero NO se muestra como
            // puntaje público al cliente — es hostil y lo espanta. Uso interno:
            // al 3er no-show se activa `requiere_confirmacion`.
            $table->integer('no_shows')->default(0);
            $table->integer('cancelaciones_tardias')->default(0);
            $table->boolean('requiere_confirmacion')->default(false);
        });

        Esquema::enum('cliente_perfil', 'genero', ['m', 'f', 'otro', 'no_decir']);

        // Tabla de parámetros: referenciada por id desde `mascota` y desde
        // `servicio_local_tamano` (Catalog) — el mismo tamaño de animal define
        // tanto la ficha de la mascota como el precio/duración que cobra el
        // local, y antes se repetía el mismo varchar+CHECK en las dos tablas.
        Schema::create('tamano_mascota', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        Schema::create('mascota', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('especie', 20);
            $table->string('raza')->nullable();
            $table->foreignUuid('tamano_id')->constrained('tamano_mascota')->restrictOnDelete();
            $table->string('pelaje', 20)->nullable();
            $table->decimal('peso_kg', 5, 2)->nullable();
            $table->string('temperamento', 20)->nullable();
            $table->text('nota')->nullable();   // "no tolera secadora"
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->index('usuario_id');
        });

        Esquema::enum('mascota', 'especie', ['perro', 'gato', 'otro']);
        Esquema::enum('mascota', 'pelaje', ['corto', 'medio', 'largo', 'rizado', 'doble_capa']);
        Esquema::enum('mascota', 'temperamento', ['tranquilo', 'nervioso', 'agresivo', 'desconocido']);

        // El consentimiento se registra POR FINALIDAD, con versión y timestamp,
        // y debe poder probarse (§13.1). Operar la cita no es lo mismo que
        // recibir promociones.
        Schema::create('consentimiento', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('finalidad', 40);
            $table->string('documento_version', 20);
            $table->boolean('otorgado');
            $table->string('origen', 10);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestampTz('otorgado_at');
            $table->timestampTz('revocado_at')->nullable();

            $table->index(['usuario_id', 'finalidad']);
        });

        Esquema::enum('consentimiento', 'finalidad', [
            'operacion_servicio',
            'comunicaciones_transaccionales',
            'marketing',
            'transferencia_internacional',
        ]);

        Esquema::enum('consentimiento', 'origen', ['app', 'local', 'web']);
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimiento');
        Schema::dropIfExists('mascota');
        Schema::dropIfExists('tamano_mascota');
        Schema::dropIfExists('cliente_perfil');
        Schema::dropIfExists('usuario');
    }
};
