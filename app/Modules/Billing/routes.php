<?php

// Rutas del modulo Billing (suscripcion, comisiones, liquidacion).
// Se montan bajo /api/v1 desde BillingServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.

use App\Modules\Billing\Http\Controllers\CobroController;
use App\Modules\Billing\Http\Controllers\LiquidacionController;
use App\Modules\Billing\Http\Controllers\SuscripcionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Solo el dueño legal del negocio (§3.2) — ni siquiera `admin`: es dinero
    // y responsabilidad fiscal, no gestión operativa del día a día.
    Route::post('negocios/{negocio}/suscripcion', [SuscripcionController::class, 'store']);
    Route::get('negocios/{negocio}/suscripcion', [SuscripcionController::class, 'show']);
    Route::post('suscripciones/{suscripcion}/cancelar', [SuscripcionController::class, 'cancelar']);

    Route::get('negocios/{negocio}/cobros', [CobroController::class, 'index']);
    Route::post('cobros/{cobro}/marcar-pagado', [CobroController::class, 'marcarPagado']);

    // Comisiones: propietario/admin, nunca recepción (§3.2).
    Route::get('locales/{local}/liquidaciones', [LiquidacionController::class, 'index']);
    Route::post('locales/{local}/liquidaciones', [LiquidacionController::class, 'store']);
    Route::post('liquidaciones/{liquidacion}/cerrar', [LiquidacionController::class, 'cerrar']);
    Route::post('liquidaciones/{liquidacion}/marcar-pagada', [LiquidacionController::class, 'marcarPagada']);
});
