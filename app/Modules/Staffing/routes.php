<?php

// Rutas del módulo Staffing (profesional, asignación, turnos, habilidades,
// recursos, excepciones). Se montan bajo /api/v1 desde StaffingServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.

use App\Modules\Staffing\Http\Controllers\AsignacionController;
use App\Modules\Staffing\Http\Controllers\ExcepcionController;
use App\Modules\Staffing\Http\Controllers\HabilidadController;
use App\Modules\Staffing\Http\Controllers\ProfesionalController;
use App\Modules\Staffing\Http\Controllers\ProfesionalFotoController;
use App\Modules\Staffing\Http\Controllers\RecursoController;
use App\Modules\Staffing\Http\Controllers\TurnoController;
use App\Modules\Staffing\Http\Controllers\TurnoFechaController;
use Illuminate\Support\Facades\Route;

// Público (§7.5): el cliente ve el perfil del profesional antes de agendar,
// sin necesitar cuenta.
Route::get('profesionales/{profesional}/perfil-publico', [ProfesionalController::class, 'perfilPublico']);

Route::middleware('auth:sanctum')->group(function () {
    // Sin `destroy`: un profesional no se borra, y su vínculo laboral
    // (`asignacion`) se termina con fecha, no se elimina (§4.2).
    Route::apiResource('locales.profesionales', ProfesionalController::class)
        ->parameters(['locales' => 'local', 'profesionales' => 'profesional'])
        ->shallow()
        ->except(['destroy']);

    // Sin `shallow()`: `locales.fotos` (Directory) ya usa la ruta plana
    // `fotos/{foto}` — si esta también fuera shallow, las dos colisionarían
    // en la misma URI y una pisaría a la otra silenciosamente. "Foto" es
    // genérico y se repite entre módulos; esta se queda anidada bajo su
    // padre en vez de aplanarse.
    Route::apiResource('profesionales.fotos', ProfesionalFotoController::class)
        ->parameters(['profesionales' => 'profesional'])
        ->except(['show']);

    // Sin `destroy`: se "termina" con fecha (`hasta`), no se borra.
    // `asignaciones` → Laravel arma `{asignacione}` en singular (regla de
    // inglés), no `{asignacion}` — mismo caso que `locales`/`locale`.
    Route::apiResource('locales.asignaciones', AsignacionController::class)
        ->parameters(['locales' => 'local', 'asignaciones' => 'asignacion'])
        ->shallow()
        ->only(['index', 'store', 'update']);
    Route::post('asignaciones/{asignacion}/terminar', [AsignacionController::class, 'terminar']);

    // Turnos recurrentes por asignación (§4.6, §4.11). El shape no es CRUD
    // clásico de un solo padre — se listan/crean bajo la asignación y se
    // actualizan/borran por su propio id — así que va explícito, sin
    // `apiResource`.
    Route::get('asignaciones/{asignacion}/turnos', [TurnoController::class, 'index']);
    Route::post('asignaciones/{asignacion}/turnos', [TurnoController::class, 'store']);
    Route::patch('turnos/{turno}', [TurnoController::class, 'update']);
    Route::delete('turnos/{turno}', [TurnoController::class, 'destroy']);

    // Recursos del local (sillas, mesas...): una fila por unidad física
    // (§4.6) — se dan de baja, nunca se borran de verdad.
    Route::apiResource('locales.recursos', RecursoController::class)
        ->parameters(['locales' => 'local', 'recursos' => 'recurso'])
        ->shallow()
        ->except(['show']);

    // Habilidades: qué servicios puede atender un profesional. Sin
    // `activo`/`estado` (§4.2): se borra de verdad. Ambos parámetros de la
    // URI van en la firma del controller aunque `destroy` no use `$profesional`
    // — dos parámetros sin `shallow()` exigen los dos en orden.
    Route::get('profesionales/{profesional}/habilidades', [HabilidadController::class, 'index']);
    Route::post('profesionales/{profesional}/habilidades', [HabilidadController::class, 'store']);
    Route::delete('profesionales/{profesional}/habilidades/{habilidad}', [HabilidadController::class, 'destroy']);

    // Overrides puntuales por fecha sobre el turno recurrente (vacaciones,
    // cambios de horario un día concreto, cancelaciones).
    Route::get('profesionales/{profesional}/turno-fechas', [TurnoFechaController::class, 'index']);
    Route::post('profesionales/{profesional}/turno-fechas', [TurnoFechaController::class, 'store']);
    Route::delete('profesionales/{profesional}/turno-fechas/{turnoFecha}', [TurnoFechaController::class, 'destroy']);

    // Excepciones: ausencia de un local, de un profesional (bloquea TODOS sus
    // locales, no solo uno — §4.6) o de un recurso. Tres orígenes de
    // creación/listado, un solo `destroy` que resuelve el dueño real.
    Route::get('locales/{local}/excepciones', [ExcepcionController::class, 'indexLocal']);
    Route::post('locales/{local}/excepciones', [ExcepcionController::class, 'storeLocal']);
    Route::get('profesionales/{profesional}/excepciones', [ExcepcionController::class, 'indexProfesional']);
    Route::post('profesionales/{profesional}/excepciones', [ExcepcionController::class, 'storeProfesional']);
    Route::get('recursos/{recurso}/excepciones', [ExcepcionController::class, 'indexRecurso']);
    Route::post('recursos/{recurso}/excepciones', [ExcepcionController::class, 'storeRecurso']);
    Route::delete('excepciones/{excepcion}', [ExcepcionController::class, 'destroy']);
});
