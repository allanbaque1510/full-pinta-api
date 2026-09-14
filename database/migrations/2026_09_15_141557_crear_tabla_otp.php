<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de un solo uso para verificar el teléfono (§4.3, §11.2).
 *
 * No está en el §4 de la especificación como tabla propia — el documento
 * menciona la verificación por OTP pero no su esquema — así que esta tabla es
 * una adición necesaria para implementar lo que sí describe: "el teléfono es
 * mejor identificador... verificación por OTP" y "reclama el registro vía OTP".
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
            $table->string('telefono', 20);
            $table->string('codigo_hash');
            $table->smallInteger('intentos')->default(0);
            $table->timestampTz('expira_at');
            $table->timestampTz('verificado_at')->nullable();
            $table->timestampTz('created_at');

            // Para encontrar rápido "el último código pendiente de este teléfono".
            $table->index(['telefono', 'created_at']);
        });

        Esquema::check('otp', 'intentos', 'intentos >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('otp');
    }
};
