<?php

// Rutas del módulo Identity (usuarios, roles, OTP, consentimientos).
// Se montan bajo /api/v1 desde IdentityServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response
// que usa el front. Este archivo solo dice QUÉ existe; el porqué de cada regla
// vive en los casos de uso y en `context/fullpinta-especificacion.md` §4.3.

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\ConsentimientoController;
use App\Modules\Identity\Http\Controllers\ContrasenaController;
use App\Modules\Identity\Http\Controllers\CuentaController;
use App\Modules\Identity\Http\Controllers\FavoritoController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // Públicas: son el propio mecanismo de entrar a la app, antes de tener
    // token. No llevan `idempotente` — un doble toque simplemente reenvía o
    // reintenta el código, no duplica ningún recurso de negocio.
    Route::post('otp/solicitar', [AuthController::class, 'solicitarOtp']);
    Route::post('otp/verificar', [AuthController::class, 'verificarOtp']);

    // Login/registro con Google y con correo/contraseña (además de OTP): el
    // teléfono sigue siendo obligatorio en los tres, y se verifica siempre
    // con el mismo `otp/solicitar` + `otp/verificar` de arriba.
    Route::post('google', [AuthController::class, 'google']);
    Route::post('registro', [AuthController::class, 'registrarConEmail']);
    Route::post('login', [AuthController::class, 'loginConEmail']);

    // Recuperar contraseña (§4.3): el usuario elige canal, teléfono (WhatsApp)
    // o correo — ambos reutilizan el mismo mecanismo de código de un solo uso
    // que `otp/solicitar`/`otp/verificar`, generalizado a aceptar cualquiera
    // de los dos destinos.
    Route::post('contrasena/olvide', [ContrasenaController::class, 'solicitarRecuperacion']);
    Route::post('contrasena/restablecer', [ContrasenaController::class, 'restablecer']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('contexto', [AuthController::class, 'contexto']);
        Route::post('logout', [AuthController::class, 'cerrarSesion']);
    });
});

// Pública, sin autenticación — el front la necesita para pintar la pantalla
// de consentimiento antes de que exista ninguna cuenta (mismo criterio que el
// catálogo maestro de Catalog).
Route::get('finalidades-consentimiento', [ConsentimientoController::class, 'finalidades']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('consentimientos', [ConsentimientoController::class, 'index']);
    Route::post('consentimientos', [ConsentimientoController::class, 'store']);

    Route::delete('cuenta', [CuentaController::class, 'eliminar']);
    Route::patch('cuenta/perfil', [CuentaController::class, 'actualizarPerfil']);
    Route::put('cuenta/contrasena', [ContrasenaController::class, 'cambiar']);
    Route::post('cuenta/email/solicitar-verificacion', [CuentaController::class, 'solicitarVerificacionEmail']);
    Route::post('cuenta/email/verificar', [CuentaController::class, 'confirmarVerificacionEmail']);

    // Favoritos (§7): un local o un profesional, nunca ambos (§4.3).
    Route::get('mis-favoritos', [FavoritoController::class, 'index']);
    Route::post('favoritos', [FavoritoController::class, 'alternar']);
});
