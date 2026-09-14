<?php

// Rutas del modulo Notifications (dispositivos, preferencias).
// Se montan bajo /api/v1 desde NotificationsServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.

use App\Modules\Notifications\Http\Controllers\DeviceTokenController;
use App\Modules\Notifications\Http\Controllers\PreferenciaNotificacionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('dispositivos', [DeviceTokenController::class, 'store']);
    Route::delete('dispositivos/{deviceToken}', [DeviceTokenController::class, 'destroy']);

    Route::get('mis-preferencias-notificacion', [PreferenciaNotificacionController::class, 'index']);
    Route::put('mis-preferencias-notificacion', [PreferenciaNotificacionController::class, 'update']);
});
