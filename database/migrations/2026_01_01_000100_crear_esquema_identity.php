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
            // Que sea UNIQUE evita que dos cuentas usen el mismo string, pero
            // no prueba que quien lo escribió controla esa bandeja (revisión
            // de base de datos, 2026-09-29) — mismo patrón que
            // `telefono_verificado`, reutilizando `otp` generalizado a email.
            $table->boolean('email_verificado')->default(false);
            $table->string('nombre');
            // La FK no garantiza que la imagen apuntada sea del propio usuario
            // — solo `ImagenService::establecerFotoPerfil()` debe escribir esta
            // columna (siempre junto con la fila de `imagen`, misma transacción).
            $table->foreignUuid('foto_perfil_id')->nullable()->constrained('imagen')->nullOnDelete();

            // Atributos de la persona, no de su relación con el marketplace —
            // le sirven igual a un dueño o a un profesional que a un cliente.
            // Antes vivían en `cliente_perfil` (revisión de base de datos,
            // 2026-09-28): esa tabla se quedó solo con lo que sí es específico
            // del comportamiento como cliente (no_shows, etc.).
            $table->string('genero', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();

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

        Esquema::enum('usuario', 'genero', ['m', 'f', 'otro', 'no_decir']);

        Schema::create('cliente_perfil', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->unique()->constrained('usuario')->cascadeOnDelete();

            // Confiabilidad del cliente: se registra pero NO se muestra como
            // puntaje público al cliente — es hostil y lo espanta. Uso interno:
            // al 3er no-show se activa `requiere_confirmacion`.
            $table->integer('no_shows')->default(0);
            $table->integer('cancelaciones_tardias')->default(0);
            $table->boolean('requiere_confirmacion')->default(false);
        });

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

        // Catálogos de mascota (revisión de base de datos, 2026-09-28): antes
        // `especie`/`raza` eran varchar libres en `mascota`. `especie` repetía
        // el mismo patrón de enum-que-en-realidad-es-catálogo ya corregido para
        // `rubro`/`amenidad_categoria`, y `raza` como texto libre no permite
        // agrupar por especie ni sembrar opciones consistentes ("Mestizo" con
        // mayúscula distinta en cada fila, etc.).
        Schema::create('especie_mascota', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->string('icono', 60)->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('raza_mascota', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('especie_id')->constrained('especie_mascota')->restrictOnDelete();
            $table->string('codigo', 60);
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->unique(['especie_id', 'codigo']);
        });

        Schema::create('mascota', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('nombre');
            $table->foreignUuid('especie_id')->constrained('especie_mascota')->restrictOnDelete();
            $table->foreignUuid('raza_id')->nullable()->constrained('raza_mascota')->restrictOnDelete();
            $table->foreignUuid('tamano_id')->constrained('tamano_mascota')->restrictOnDelete();
            $table->string('pelaje', 20)->nullable();
            $table->decimal('peso_kg', 5, 2)->nullable();
            $table->string('temperamento', 20)->nullable();
            $table->text('nota')->nullable();   // "no tolera secadora"
            $table->foreignUuid('foto_perfil_id')->nullable()->constrained('imagen')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->index('usuario_id');
        });

        Esquema::enum('mascota', 'pelaje', ['corto', 'medio', 'largo', 'rizado', 'doble_capa']);
        Esquema::enum('mascota', 'temperamento', ['tranquilo', 'nervioso', 'agresivo', 'desconocido']);

        // Catálogo de finalidades del consentimiento (§13.1, revisión de base
        // de datos, 2026-09-28): antes `finalidad` era varchar+CHECK sin texto
        // propio — el nombre/descripción que ve el usuario en la pantalla de
        // consentimiento estaba hardcodeado en el Flutter, no en la base.
        // Cambiar ese texto exigía desplegar la app.
        Schema::create('finalidad_consentimiento', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 40)->unique();   // operacion_servicio, comunicaciones_transaccionales, marketing, transferencia_internacional
            $table->string('nombre');
            $table->text('descripcion');
            $table->boolean('obligatorio')->default(false);
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            // Qué tipo de `documento_legal` respalda esta finalidad, si alguno
            // (§13.1, pregunta 10) — nullable a propósito: cuáles finalidades
            // necesitan documento formal es decisión legal, todavía pendiente
            // de confirmar. No es FK: `documento_legal` se identifica por
            // (tipo, version), no por tipo solo — este campo solo dice "qué
            // tipo buscar", `OtorgarConsentimiento` resuelve la versión vigente.
            $table->string('documento_tipo', 30)->nullable();
        });

        // El documento legal en sí (política de privacidad, términos...) —
        // antes `consentimiento.documento_version` era un string suelto sin
        // contenido ni URL asociados. Inmutable una vez publicado, mismo
        // criterio que `consentimiento`: una versión nueva es una fila nueva,
        // nunca se edita una ya publicada. Una finalidad no está atada 1:1 a
        // un documento — varias finalidades pueden compartir el mismo (por
        // eso es tabla propia, no una columna más de `finalidad_consentimiento`).
        Schema::create('documento_legal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipo', 30);
            $table->string('version', 20);
            $table->text('contenido');   // texto completo, markdown
            $table->string('url')->nullable();   // página pública equivalente, opcional — no es la fuente de verdad legal
            $table->timestampTz('vigente_desde');
            $table->timestampTz('vigente_hasta')->nullable();

            $table->unique(['tipo', 'version']);
        });

        Esquema::enum('documento_legal', 'tipo', [
            'politica_privacidad',
            'politica_marketing',
            'terminos_condiciones',
        ]);

        Esquema::enum('finalidad_consentimiento', 'documento_tipo', [
            'politica_privacidad',
            'politica_marketing',
            'terminos_condiciones',
        ]);

        // El consentimiento se registra POR FINALIDAD, con versión y timestamp,
        // y debe poder probarse (§13.1). Operar la cita no es lo mismo que
        // recibir promociones.
        Schema::create('consentimiento', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignUuid('finalidad_id')->constrained('finalidad_consentimiento')->restrictOnDelete();
            // Nullable a propósito (§13.1, pregunta 10): no toda finalidad
            // necesita un documento legal formal detrás — cuáles sí es
            // decisión legal, no técnica, todavía pendiente de confirmar.
            $table->foreignUuid('documento_legal_id')->nullable()->constrained('documento_legal')->restrictOnDelete();
            $table->boolean('otorgado');
            $table->string('origen', 10);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestampTz('otorgado_at');
            $table->timestampTz('revocado_at')->nullable();

            $table->index(['usuario_id', 'finalidad_id']);
        });

        Esquema::enum('consentimiento', 'origen', ['app', 'local', 'web']);
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimiento');
        Schema::dropIfExists('documento_legal');
        Schema::dropIfExists('finalidad_consentimiento');
        Schema::dropIfExists('mascota');
        Schema::dropIfExists('raza_mascota');
        Schema::dropIfExists('especie_mascota');
        Schema::dropIfExists('tamano_mascota');
        Schema::dropIfExists('cliente_perfil');
        Schema::dropIfExists('usuario');
    }
};
