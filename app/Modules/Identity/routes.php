<?php

// Rutas del módulo Identity (usuarios, roles, OTP, consentimientos).
// Se montan bajo /api/v1 desde IdentityServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response
// que usa el front. Este archivo solo dice QUÉ existe; el porqué de cada regla
// vive en los casos de uso y en `context/fullpinta-especificacion.md` §4.3.

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\ConsentimientoController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('contexto', [AuthController::class, 'contexto']);
        Route::post('logout', [AuthController::class, 'cerrarSesion']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('consentimientos', [ConsentimientoController::class, 'index']);
    Route::post('consentimientos', [ConsentimientoController::class, 'store']);

    Route::delete('cuenta', [CuentaController::class, 'eliminar']);

    // Favoritos (§7): un local o un profesional, nunca ambos (§4.3).
    Route::get('mis-favoritos', [FavoritoController::class, 'index']);
    Route::post('favoritos', [FavoritoController::class, 'alternar']);
});
