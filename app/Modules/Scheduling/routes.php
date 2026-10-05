<?php

// Rutas del modulo Scheduling (disponibilidad, citas, holds, waitlist).
// Se montan bajo /api/v1 desde SchedulingServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.
// Dos grupos: el primero exige el permiso de rol de su propia ruta (§3.2,
// ver `App\Http\Middleware\VerificarPermiso`); el segundo es la agenda de
// una cita puntual — casi toda combinada (el cliente dueño, O el staff con
// permiso, O el profesional asignado) — autorizada dentro del controller.

use App\Modules\Scheduling\Http\Controllers\CitaController;
use App\Modules\Scheduling\Http\Controllers\ClienteLocalController;
use App\Modules\Scheduling\Http\Controllers\DisponibilidadController;
use App\Modules\Scheduling\Http\Controllers\EsperaController;
use Illuminate\Support\Facades\Route;

// Público: el cliente necesita ver horarios antes de tener cuenta.
Route::get('locales/{local}/disponibilidad', [DisponibilidadController::class, 'index'])->name('locales.disponibilidad.index');

// --- Permiso por rol (§3.2) ------------------------------------------------
Route::middleware(['auth:sanctum', 'permiso'])->group(function () {
    Route::get('locales/{local}/citas', [CitaController::class, 'index'])->name('locales.citas.index');

    // Agendar y el walk-in exigen `Idempotency-Key` (§12.4): en móvil la red
    // se cae a mitad del POST y el usuario vuelve a tocar el botón.
    Route::post('locales/{local}/citas', [CitaController::class, 'store'])
        ->middleware('idempotente')->name('locales.citas.store');
    Route::post('locales/{local}/citas/walk-in', [CitaController::class, 'walkIn'])
        ->middleware('idempotente')->name('locales.citas.walk-in.store');

    Route::get('locales/{local}/esperas', [EsperaController::class, 'index'])->name('locales.esperas.index');

    // Ficha del cliente (§4.7): continuidad de servicio, no calificación —
    // `nota`/`profesional_preferido`, más el resumen de visitas calculado en
    // vivo contra `cita`.
    Route::get('locales/{local}/clientes', [ClienteLocalController::class, 'index'])->name('locales.clientes.index');
    Route::get('locales/{local}/clientes/{usuario}', [ClienteLocalController::class, 'show'])
        ->name('locales.clientes.show');
    Route::put('locales/{local}/clientes/{usuario}', [ClienteLocalController::class, 'update'])
        ->name('locales.clientes.update');
});

// --- Propiedad puntual / combinado con rol ---------------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Historial del cliente (§7.6) y agenda propia del profesional (§3.2) —
    // cruzan todos los locales, sin scoping a uno.
    Route::get('mis-citas', [CitaController::class, 'misCitas'])->name('mis-citas');
    Route::get('mis-citas-profesional', [CitaController::class, 'misCitasProfesional'])->name('mis-citas-profesional');

    // El resto de la agenda de una cita puntual combina "¿es suya?" con
    // "¿tiene el permiso de rol?" — se autoriza dentro del `FormRequest`/controller.
    Route::get('citas/{cita}', [CitaController::class, 'show'])->name('citas.show');
    Route::post('citas/{cita}/confirmar', [CitaController::class, 'confirmar'])->name('citas.confirmar');
    Route::post('citas/{cita}/iniciar', [CitaController::class, 'iniciar'])->name('citas.iniciar');
    Route::post('citas/{cita}/completar', [CitaController::class, 'completar'])->name('citas.completar');
    Route::post('citas/{cita}/cancelar', [CitaController::class, 'cancelar'])
        ->middleware('idempotente')->name('citas.cancelar');
    Route::post('citas/{cita}/no-show', [CitaController::class, 'noShow'])->name('citas.no-show');
    Route::post('citas/{cita}/reagendar', [CitaController::class, 'reagendar'])->name('citas.reagendar');
    Route::post('citas/{cita}/productos', [CitaController::class, 'agregarProducto'])->name('citas.productos.store');

    // Cualquier cliente autenticado puede anotarse en la lista de espera, sin permiso de rol.
    Route::post('locales/{local}/esperas', [EsperaController::class, 'store'])->name('locales.esperas.store');
});
