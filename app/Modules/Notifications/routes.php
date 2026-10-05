<?php

// Rutas del modulo Notifications (dispositivos, preferencias).
// Se montan bajo /api/v1 desde NotificationsServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.

use App\Modules\Notifications\Http\Controllers\DeviceTokenController;
use App\Modules\Notifications\Http\Controllers\PreferenciaNotificacionController;
use Illuminate\Support\Facades\Route;

// Todo propiedad puntual (mis dispositivos, mis preferencias) — sin permiso de rol.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('dispositivos', [DeviceTokenController::class, 'store'])->name('dispositivos.store');
    Route::delete('dispositivos/{deviceToken}', [DeviceTokenController::class, 'destroy'])
        ->name('dispositivos.destroy');

    Route::get('mis-preferencias-notificacion', [PreferenciaNotificacionController::class, 'index'])->name('mis-preferencias-notificacion.index');
    Route::put('mis-preferencias-notificacion', [PreferenciaNotificacionController::class, 'update'])->name('mis-preferencias-notificacion.update');
});
