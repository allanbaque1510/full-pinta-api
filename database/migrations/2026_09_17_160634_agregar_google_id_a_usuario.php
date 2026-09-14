<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login con Google (además de teléfono+OTP y correo/contraseña): `google_id`
 * es el `sub` del token, el identificador estable de Google — nunca el
 * email, que puede cambiar de lado de la cuenta de Google.
 *
 * `usuario.telefono` no se toca: sigue siendo obligatorio para cualquier
 * método de registro. La verificación del teléfono sigue siendo, para los
 * tres métodos, el mismo flujo de OTP que ya existía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
