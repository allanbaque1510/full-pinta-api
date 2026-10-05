<?php

// Rutas del modulo Reviews (resenas, ranking, moderacion).
// Se montan bajo /api/v1 desde ReviewsServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.
// Dos grupos: el primero exige el permiso de rol de su propia ruta (§3.2,
// ver `App\Http\Middleware\VerificarPermiso`); el segundo es propiedad
// puntual (solo el cliente dueño de la cita) o sin restricción de rol.

use App\Modules\Reviews\Http\Controllers\ReporteController;
use App\Modules\Reviews\Http\Controllers\ResenaController;
use Illuminate\Support\Facades\Route;

// --- Permiso por rol (§3.2) ------------------------------------------------
Route::middleware(['auth:sanctum', 'permiso'])->group(function () {
    Route::get('locales/{local}/resenas', [ResenaController::class, 'index'])->name('locales.resenas.index');
    Route::post('resenas/{resena}/responder', [ResenaController::class, 'responder'])->name('resenas.responder');
});

// --- Propiedad puntual / sin restricción de rol ----------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Propiedad puntual: solo el cliente dueño de la cita puede reseñarla.
    Route::post('citas/{cita}/resenas', [ResenaController::class, 'store'])->name('resenas.store');

    // Cualquier usuario autenticado puede reportar contenido — no hay
    // noción de "dueño" aquí, sin permiso de rol.
    Route::post('reportes', [ReporteController::class, 'store'])->name('reportes.store');
});
