<?php

// Rutas del módulo Directory (negocio, local, amenidades, verificación).
// Se montan bajo /api/v1 desde DirectoryServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.
// Dos grupos: el primero exige el permiso de rol de su propia ruta (§3.2,
// ver `App\Http\Middleware\VerificarPermiso`); el segundo no depende de rol
// (crear el propio negocio, o el dueño que la ruta resuelve polimórficamente).

use App\Http\Controllers\ImagenController;
use App\Modules\Directory\Http\Controllers\AmenidadController;
use App\Modules\Directory\Http\Controllers\BusquedaLocalController;
use App\Modules\Directory\Http\Controllers\HorarioLocalController;
use App\Modules\Directory\Http\Controllers\LocalAmenidadController;
use App\Modules\Directory\Http\Controllers\LocalController;
use App\Modules\Directory\Http\Controllers\NegocioController;
use App\Modules\Directory\Http\Controllers\NegocioMiembroController;
use Illuminate\Support\Facades\Route;

// Público: el catálogo de amenidades lo define la plataforma, no hace falta
// estar autenticado para verlo (el front lo usa para armar pickers).
Route::get('amenidades', [AmenidadController::class, 'index'])->name('amenidades.index');

// Públicas (§7): la búsqueda y el perfil del local son la puerta de entrada
// del cliente, antes de tener cuenta.
Route::get('buscar/locales', [BusquedaLocalController::class, 'index'])->name('buscar.locales');
Route::get('locales/{local}/perfil-publico', [LocalController::class, 'perfilPublico'])->name('locales.perfil-publico');

// --- Permiso por rol (§3.2) ------------------------------------------------
Route::middleware(['auth:sanctum', 'permiso'])->group(function () {
    Route::get('negocios/{negocio}', [NegocioController::class, 'show'])->name('negocios.show');
    Route::patch('negocios/{negocio}', [NegocioController::class, 'update'])->name('negocios.update');

    // Acceso real a la app (admin/recepción), no la ficha de trabajo del
    // profesional — ver docblock de `NegocioMiembroController`. Resuelve por
    // `telefono` de una cuenta ya registrada, sin invitación por link en v1.
    Route::get('negocios/{negocio}/miembros', [NegocioMiembroController::class, 'index'])
        ->name('negocios.miembros.index');
    Route::post('negocios/{negocio}/miembros', [NegocioMiembroController::class, 'store'])
        ->name('negocios.miembros.store');
    Route::post('miembros/{miembro}/terminar', [NegocioMiembroController::class, 'terminar'])
        ->name('miembros.terminar');

    Route::get('negocios/{negocio}/locales', [LocalController::class, 'index'])->name('negocios.locales.index');
    Route::post('negocios/{negocio}/locales', [LocalController::class, 'store'])->name('negocios.locales.store');
    // Sin `destroy`: un local no se borra, cambia de `estado` (§4.4), por eso
    // `activar`/`pausar` van aparte.
    Route::get('locales/{local}', [LocalController::class, 'show'])->name('locales.show');
    Route::patch('locales/{local}', [LocalController::class, 'update'])->name('locales.update');
    Route::post('locales/{local}/activar', [LocalController::class, 'activar'])->name('locales.activar');
    Route::post('locales/{local}/pausar', [LocalController::class, 'pausar'])->name('locales.pausar');

    // Horarios: `update`/`destroy` resuelven el local vía la relación
    // `horario->local` (el middleware `permiso` la sigue solo).
    Route::get('locales/{local}/horarios', [HorarioLocalController::class, 'index'])->name('locales.horarios.index');
    Route::post('locales/{local}/horarios', [HorarioLocalController::class, 'store'])->name('locales.horarios.store');
    Route::patch('horarios/{horario}', [HorarioLocalController::class, 'update'])->name('horarios.update');
    Route::delete('horarios/{horario}', [HorarioLocalController::class, 'destroy'])->name('horarios.destroy');

    // Galería polimórfica compartida con Staffing (§4.4) — `ImagenController`
    // no vive en ningún módulo. `update`/`destroy` (planas, `imagenes/{imagen}`)
    // van en el grupo sin permiso de rol: el dueño es polimórfico.
    Route::get('locales/{local}/imagenes', [ImagenController::class, 'indexLocal'])->name('locales.imagenes.index');
    Route::post('locales/{local}/imagenes', [ImagenController::class, 'storeLocal'])->name('locales.imagenes.store');

    // No es un CRUD de un solo recurso por id: PUT reemplaza el conjunto
    // completo de una vez (§4.4), así que no encaja en apiResource.
    Route::get('locales/{local}/amenidades', [LocalAmenidadController::class, 'index'])
        ->name('locales.amenidades.index');
    Route::put('locales/{local}/amenidades', [LocalAmenidadController::class, 'update'])
        ->name('locales.amenidades.update');
});

// --- Sin permiso de rol -----------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    // No hay `index` (listar TODOS los negocios sería un leak de datos de
    // negocio) ni `destroy` (un negocio no se borra, ver §4.2). Cualquier
    // usuario autenticado puede crear su propio negocio.
    Route::post('negocios', [NegocioController::class, 'store'])->name('negocios.store');

    // `update`/`destroy` son planas (`imagenes/{imagen}`) y se registran UNA
    // sola vez acá: si Staffing también las declarara, las dos generarían la
    // misma URI y una pisaría a la otra en silencio. Su permiso depende del
    // tipo de dueño (`local` o `profesional`), conocido solo en tiempo de
    // ejecución — se autorizan dentro del controller.
    Route::patch('imagenes/{imagen}', [ImagenController::class, 'update'])->name('imagenes.update');
    Route::delete('imagenes/{imagen}', [ImagenController::class, 'destroy'])->name('imagenes.destroy');
});
