<?php

// Rutas del módulo Directory (negocio, local, amenidades, verificación).
// Se montan bajo /api/v1 desde DirectoryServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.

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
Route::get('amenidades', [AmenidadController::class, 'index']);

// Públicas (§7): la búsqueda y el perfil del local son la puerta de entrada
// del cliente, antes de tener cuenta.
Route::get('buscar/locales', [BusquedaLocalController::class, 'index']);
Route::get('locales/{local}/perfil-publico', [LocalController::class, 'perfilPublico']);

Route::middleware('auth:sanctum')->group(function () {
    // No hay `index` (listar TODOS los negocios sería un leak de datos de
    // negocio) ni `destroy` (un negocio no se borra, ver §4.2).
    Route::apiResource('negocios', NegocioController::class)->only(['store', 'show', 'update']);

    // Acceso real a la app (admin/recepción), no la ficha de trabajo del
    // profesional — ver docblock de `NegocioMiembroController`. Resuelve por
    // `telefono` de una cuenta ya registrada, sin invitación por link en v1.
    Route::get('negocios/{negocio}/miembros', [NegocioMiembroController::class, 'index']);
    Route::post('negocios/{negocio}/miembros', [NegocioMiembroController::class, 'store']);
    Route::post('miembros/{miembro}/terminar', [NegocioMiembroController::class, 'terminar']);

    // Nido "shallow": crear/listar locales cuelga del negocio
    // (`negocios/{negocio}/locales`), pero ver/editar un local ya no necesita
    // el negocio en la URL (`locales/{local}`) — es el mismo shape que ya
    // tenían estas rutas escritas a mano. Sin `destroy`: un local no se
    // borra, cambia de `estado` (§4.4), por eso `activar`/`pausar` van aparte.
    //
    // `parameters`: el singular en inglés de "locales" es "locale", no
    // "local" — sin esto, Laravel genera `{locale}` en la URL y el binding
    // implícito nunca coincide con el `Local $local` de los controladores.
    Route::apiResource('negocios.locales', LocalController::class)
        ->parameters(['locales' => 'local'])
        ->shallow()
        ->except(['destroy']);
    Route::post('locales/{local}/activar', [LocalController::class, 'activar']);
    Route::post('locales/{local}/pausar', [LocalController::class, 'pausar']);

    // Mismo caso de `parameters`: sin esto el segmento padre de la URL sale
    // como `{locale}` y no coincide con `Local $local` en el controlador.
    // Sin `show`: no hace falta consultar un horario suelto por id, siempre
    // se listan todos los del local.
    Route::apiResource('locales.horarios', HorarioLocalController::class)
        ->parameters(['locales' => 'local'])
        ->shallow()
        ->except(['show']);

    // Galería polimórfica compartida con Staffing (§4.4) — `ImagenController`
    // no vive en ningún módulo, ninguno es dueño único de este dato. `update`/
    // `destroy` (`imagenes/{imagen}`) se registran UNA sola vez aquí: si
    // Staffing también las declarara, las dos generarían la misma URI y una
    // pisaría a la otra en silencio.
    Route::get('locales/{local}/imagenes', [ImagenController::class, 'indexLocal']);
    Route::post('locales/{local}/imagenes', [ImagenController::class, 'storeLocal']);
    Route::patch('imagenes/{imagen}', [ImagenController::class, 'update']);
    Route::delete('imagenes/{imagen}', [ImagenController::class, 'destroy']);

    // No es un CRUD de un solo recurso por id: PUT reemplaza el conjunto
    // completo de una vez (§4.4), así que no encaja en apiResource.
    Route::get('locales/{local}/amenidades', [LocalAmenidadController::class, 'index']);
    Route::put('locales/{local}/amenidades', [LocalAmenidadController::class, 'update']);
});
