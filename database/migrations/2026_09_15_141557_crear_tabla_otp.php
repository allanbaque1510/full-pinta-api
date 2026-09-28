<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de un solo uso para verificar un teléfono o un correo (§4.3, §11.2,
 * §13.1).
 *
 * No está en el §4 de la especificación como tabla propia — el documento
 * menciona la verificación por OTP pero no su esquema — así que esta tabla es
 * una adición necesaria para implementar lo que sí describe: "el teléfono es
 * mejor identificador... verificación por OTP" y "reclama el registro vía OTP".
 *
 * `email` se agregó después (revisión de base de datos, 2026-09-29): el mismo
 * mecanismo de código de un solo uso sirve para probar que alguien controla un
 * correo, sea para verificarlo o para recuperar la contraseña por ese canal —
 * exactamente uno de los dos destinos, nunca ninguno ni ambos (mismo patrón
 * XOR que `favorito`).
 *
 * El código NUNCA se guarda en claro, igual que una contraseña: se guarda su
 * hash y se compara con `Hash::check()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('telefono', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('codigo_hash');
            $table->smallInteger('intentos')->default(0);
            $table->timestampTz('expira_at');
            $table->timestampTz('verificado_at')->nullable();
            $table->timestampTz('created_at');

            // Para encontrar rápido "el último código pendiente" de cada destino.
            $table->index(['telefono', 'created_at']);
            $table->index(['email', 'created_at']);
        });

        Esquema::check('otp', 'intentos', 'intentos >= 0');
        Esquema::check('otp', 'destino', '(telefono IS NOT NULL) <> (email IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('otp');
    }
};
