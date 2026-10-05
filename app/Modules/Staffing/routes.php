<?php

// Rutas del módulo Staffing (profesional, asignación, turnos, habilidades,
// recursos, excepciones). Se montan bajo /api/v1 desde StaffingServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.
// Dos grupos: el primero exige el permiso de rol de su propia ruta (§3.2,
// ver `App\Http\Middleware\VerificarPermiso`); el segundo son rutas donde
// además (o únicamente) importa la propiedad puntual — el propio profesional
// actuando sobre sí mismo — autorizadas dentro del controller.

use App\Http\Controllers\ImagenController;
use App\Modules\Staffing\Http\Controllers\AsignacionController;
use App\Modules\Staffing\Http\Controllers\ExcepcionController;
use App\Modules\Staffing\Http\Controllers\HabilidadController;
use App\Modules\Staffing\Http\Controllers\ProfesionalController;
use App\Modules\Staffing\Http\Controllers\RecursoController;
use App\Modules\Staffing\Http\Controllers\TurnoController;
use App\Modules\Staffing\Http\Controllers\TurnoFechaController;
use Illuminate\Support\Facades\Route;

// Público (§7.5): el cliente ve el perfil del profesional antes de agendar,
// sin necesitar cuenta.
Route::get('profesionales/{profesional}/perfil-publico', [ProfesionalController::class, 'perfilPublico'])
    ->name('profesionales.perfil-publico');

// --- Permiso por rol (§3.2) ------------------------------------------------
Route::middleware(['auth:sanctum', 'permiso'])->group(function () {
    // Sin `destroy`: un profesional no se borra, y su vínculo laboral
    // (`asignacion`) se termina con fecha, no se elimina (§4.2).
    Route::get('locales/{local}/profesionales', [ProfesionalController::class, 'index'])
        ->name('locales.profesionales.index');
    Route::post('locales/{local}/profesionales', [ProfesionalController::class, 'store'])
        ->name('locales.profesionales.store');
    Route::get('profesionales/{profesional}', [ProfesionalController::class, 'show'])
        ->name('profesionales.show');

    // Vincular/cambiar la cuenta de acceso de un profesional (§4.6) — solo
    // quien administra, deliberadamente NUNCA el propio profesional.
    Route::post('profesionales/{profesional}/vincular-cuenta', [ProfesionalController::class, 'vincularCuenta'])
        ->name('profesionales.vincular-cuenta');

    Route::get('locales/{local}/asignaciones', [AsignacionController::class, 'index'])
        ->name('locales.asignaciones.index');
    Route::post('locales/{local}/asignaciones', [AsignacionController::class, 'store'])
        ->name('locales.asignaciones.store');
    Route::patch('asignaciones/{asignacion}', [AsignacionController::class, 'update'])
        ->name('asignaciones.update');
    Route::post('asignaciones/{asignacion}/terminar', [AsignacionController::class, 'terminar'])
        ->name('asignaciones.terminar');

    // Turnos recurrentes por asignación (§4.6, §4.11).
    Route::get('asignaciones/{asignacion}/turnos', [TurnoController::class, 'index'])
        ->name('locales.asignaciones.turnos.index');
    Route::post('asignaciones/{asignacion}/turnos', [TurnoController::class, 'store'])
        ->name('locales.asignaciones.turnos.store');
    Route::patch('turnos/{turno}', [TurnoController::class, 'update'])->name('locales.turnos.update');
    Route::delete('turnos/{turno}', [TurnoController::class, 'destroy'])->name('locales.turnos.destroy');

    // Recursos del local (sillas, mesas...): una fila por unidad física
    // (§4.6) — se dan de baja, nunca se borran de verdad.
    Route::get('locales/{local}/recursos', [RecursoController::class, 'index'])->name('locales.recursos.index');
    Route::post('locales/{local}/recursos', [RecursoController::class, 'store'])->name('locales.recursos.store');
    Route::patch('recursos/{recurso}', [RecursoController::class, 'update'])->name('locales.recursos.update');
    Route::delete('recursos/{recurso}', [RecursoController::class, 'destroy'])->name('locales.recursos.destroy');

    Route::get('profesionales/{profesional}/habilidades', [HabilidadController::class, 'index'])
        ->name('profesionales.habilidades.index');

    Route::get('profesionales/{profesional}/turno-fechas', [TurnoFechaController::class, 'index'])
        ->name('profesionales.turno-fechas.index');

    Route::get('locales/{local}/excepciones', [ExcepcionController::class, 'indexLocal'])
        ->name('locales.excepciones.index');
    Route::post('locales/{local}/excepciones', [ExcepcionController::class, 'storeLocal'])
        ->name('locales.excepciones.store');
    Route::get('profesionales/{profesional}/excepciones', [ExcepcionController::class, 'indexProfesional'])
        ->name('profesionales.excepciones.index');
    Route::get('recursos/{recurso}/excepciones', [ExcepcionController::class, 'indexRecurso'])
        ->name('locales.recursos.excepciones.index');
    Route::post('recursos/{recurso}/excepciones', [ExcepcionController::class, 'storeRecurso'])
        ->name('locales.recursos.excepciones.store');
});

// --- Propiedad puntual: el propio profesional, además o en vez del rol ----
Route::middleware('auth:sanctum')->group(function () {
    // Editar su propia ficha: quien administra alguno de sus locales, O el
    // propio profesional (§3.2) — combinado en el controller.
    Route::patch('profesionales/{profesional}', [ProfesionalController::class, 'update'])
        ->name('profesionales.update');

    // Galería polimórfica compartida con Directory (§4.4). Combinado (igual
    // que editar la ficha): quien administra, o el propio profesional.
    Route::get('profesionales/{profesional}/imagenes', [ImagenController::class, 'indexProfesional'])
        ->name('profesionales.imagenes.index');
    Route::post('profesionales/{profesional}/imagenes', [ImagenController::class, 'storeProfesional'])
        ->name('profesionales.imagenes.store');

    // `store` autoriza contra el LOCAL del servicio/body (resuelto dentro
    // del controller, no hay parámetro de ruta que seguir). `destroy`
    // combina rol U propiedad.
    Route::post('profesionales/{profesional}/habilidades', [HabilidadController::class, 'store'])
        ->name('profesionales.habilidades.store');
    Route::delete('profesionales/{profesional}/habilidades/{habilidad}', [HabilidadController::class, 'destroy'])
        ->name('profesionales.habilidades.destroy');

    Route::post('profesionales/{profesional}/turno-fechas', [TurnoFechaController::class, 'store'])
        ->name('profesionales.turno-fechas.store');
    Route::delete('profesionales/{profesional}/turno-fechas/{turnoFecha}', [TurnoFechaController::class, 'destroy'])
        ->name('profesionales.turno-fechas.destroy');

    Route::post('profesionales/{profesional}/excepciones', [ExcepcionController::class, 'storeProfesional'])
        ->name('profesionales.excepciones.store');

    // Resuelve el dueño real y combina rol U propiedad según el tipo.
    Route::delete('excepciones/{excepcion}', [ExcepcionController::class, 'destroy'])->name('excepciones.destroy');
});
