<?php

// Rutas del modulo Reviews (resenas, ranking, moderacion).
// Se montan bajo /api/v1 desde ReviewsServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.

use App\Modules\Reviews\Http\Controllers\ReporteController;
use App\Modules\Reviews\Http\Controllers\ResenaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('citas/{cita}/resenas', [ResenaController::class, 'store']);
    Route::get('locales/{local}/resenas', [ResenaController::class, 'index']);
    Route::post('resenas/{resena}/responder', [ResenaController::class, 'responder']);

    Route::post('reportes', [ReporteController::class, 'store']);
});
