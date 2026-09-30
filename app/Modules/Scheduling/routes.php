<?php

// Rutas del modulo Scheduling (disponibilidad, citas, holds, waitlist).
// Se montan bajo /api/v1 desde SchedulingServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.

use App\Modules\Scheduling\Http\Controllers\CitaController;
use App\Modules\Scheduling\Http\Controllers\ClienteLocalController;
use App\Modules\Scheduling\Http\Controllers\DisponibilidadController;
use App\Modules\Scheduling\Http\Controllers\EsperaController;
use Illuminate\Support\Facades\Route;

// Público: el cliente necesita ver horarios antes de tener cuenta.
Route::get('locales/{local}/disponibilidad', [DisponibilidadController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    // Historial del cliente (§7.6): cruza todos los locales que visitó, sin
    // scoping a uno.
    Route::get('mis-citas', [CitaController::class, 'misCitas']);

    // Agenda propia del profesional (§3.2) — igual que arriba, sin scoping a
    // un solo local: puede trabajar en varios a la vez (§4.6).
    Route::get('mis-citas-profesional', [CitaController::class, 'misCitasProfesional']);

    Route::get('locales/{local}/citas', [CitaController::class, 'index']);
    Route::get('citas/{cita}', [CitaController::class, 'show']);

    // Agendar y cancelar exigen `Idempotency-Key` (§12.4): en móvil la red se
    // cae a mitad del POST y el usuario vuelve a tocar el botón.
    Route::post('locales/{local}/citas', [CitaController::class, 'store'])->middleware('idempotente');
    Route::post('locales/{local}/citas/walk-in', [CitaController::class, 'walkIn'])->middleware('idempotente');

    Route::post('citas/{cita}/confirmar', [CitaController::class, 'confirmar']);
    Route::post('citas/{cita}/iniciar', [CitaController::class, 'iniciar']);
    Route::post('citas/{cita}/completar', [CitaController::class, 'completar']);
    Route::post('citas/{cita}/cancelar', [CitaController::class, 'cancelar'])->middleware('idempotente');
    Route::post('citas/{cita}/no-show', [CitaController::class, 'noShow']);
    Route::post('citas/{cita}/reagendar', [CitaController::class, 'reagendar']);
    Route::post('citas/{cita}/productos', [CitaController::class, 'agregarProducto']);

    Route::get('locales/{local}/esperas', [EsperaController::class, 'index']);
    Route::post('locales/{local}/esperas', [EsperaController::class, 'store']);

    // Ficha del cliente (§4.7): continuidad de servicio, no calificación —
    // `nota`/`profesional_preferido`, más el resumen de visitas calculado en
    // vivo contra `cita`.
    Route::get('locales/{local}/clientes', [ClienteLocalController::class, 'index']);
    Route::get('locales/{local}/clientes/{usuario}', [ClienteLocalController::class, 'show']);
    Route::put('locales/{local}/clientes/{usuario}', [ClienteLocalController::class, 'update']);
});
