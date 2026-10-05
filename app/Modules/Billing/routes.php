<?php

// Rutas del modulo Billing (suscripcion, comisiones, liquidacion).
// Se montan bajo /api/v1 desde BillingServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.
// Dos grupos: el primero exige el permiso de rol de su propia ruta (§3.2,
// ver `App\Http\Middleware\VerificarPermiso`); el segundo es propiedad
// puntual (dueño legal del negocio, o el propio profesional) — se autoriza
// dentro del controller, nunca por rol.

use App\Modules\Billing\Http\Controllers\CobroController;
use App\Modules\Billing\Http\Controllers\LiquidacionController;
use App\Modules\Billing\Http\Controllers\SuscripcionController;
use Illuminate\Support\Facades\Route;

// --- Permiso por rol (§3.2) ------------------------------------------------
Route::middleware(['auth:sanctum', 'permiso'])->group(function () {
    // Comisiones: propietario/admin, nunca recepción (§3.2).
    Route::get('locales/{local}/liquidaciones', [LiquidacionController::class, 'index'])
        ->name('locales.liquidaciones.index');
    Route::post('locales/{local}/liquidaciones', [LiquidacionController::class, 'store'])
        ->name('locales.liquidaciones.store');
    Route::post('liquidaciones/{liquidacion}/cerrar', [LiquidacionController::class, 'cerrar'])
        ->name('liquidaciones.cerrar');
    Route::post('liquidaciones/{liquidacion}/marcar-pagada', [LiquidacionController::class, 'marcarPagada'])
        ->name('liquidaciones.marcar-pagada');
});

// --- Propiedad puntual (dueño legal del negocio, o el propio profesional) -
Route::middleware('auth:sanctum')->group(function () {
    // Solo el dueño legal del negocio (§3.2) — ni siquiera `admin`: es dinero
    // y responsabilidad fiscal, no gestión operativa del día a día.
    Route::post('negocios/{negocio}/suscripcion', [SuscripcionController::class, 'store'])
        ->name('negocios.suscripcion.store');
    Route::get('negocios/{negocio}/suscripcion', [SuscripcionController::class, 'show'])
        ->name('negocios.suscripcion.show');
    Route::post('suscripciones/{suscripcion}/cancelar', [SuscripcionController::class, 'cancelar'])
        ->name('suscripciones.cancelar');

    Route::get('negocios/{negocio}/cobros', [CobroController::class, 'index'])->name('negocios.cobros.index');
    Route::post('cobros/{cobro}/marcar-pagado', [CobroController::class, 'marcarPagado'])
        ->name('cobros.marcar-pagado');
    Route::post('cobros/{cobro}/marcar-reembolsado', [CobroController::class, 'marcarReembolsado'])
        ->name('cobros.marcar-reembolsado');

    // Sus propias comisiones (§3.2) — solo el propio profesional, nunca
    // propietario/admin de otro local que también lo emplee (ver docblock de
    // `LiquidacionController::indexProfesional`).
    Route::get('profesionales/{profesional}/liquidaciones', [LiquidacionController::class, 'indexProfesional'])
        ->name('profesionales.liquidaciones.index');
});
