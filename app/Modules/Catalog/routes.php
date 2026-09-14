<?php

// Rutas del módulo Catalog (catálogo maestro, servicios del local, precios).
// Se montan bajo /api/v1 desde CatalogServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.

use App\Modules\Catalog\Http\Controllers\CatalogoController;
use App\Modules\Catalog\Http\Controllers\ProductoController;
use App\Modules\Catalog\Http\Controllers\ServicioLocalController;
use App\Modules\Catalog\Http\Controllers\SolicitudCatalogoController;
use Illuminate\Support\Facades\Route;

// Público: el catálogo lo define la plataforma, no los locales (§4.5). El
// front lo necesita para armar el picker antes de crear un servicio_local.
Route::get('catalogo/categorias', [CatalogoController::class, 'categorias']);
Route::get('catalogo/servicios', [CatalogoController::class, 'servicios']);

Route::middleware('auth:sanctum')->group(function () {
    // Sin `show`: el listado siempre trae los servicios completos del local,
    // no hace falta consultar uno suelto por id. `destroy` desactiva
    // (`activo=false`, §4.2) en vez de borrar — ver
    // `ServicioLocalService::desactivar()` — pero eso es un detalle del
    // servicio, no de la ruta: sigue siendo un `DELETE` REST normal.
    Route::apiResource('locales.servicios', ServicioLocalController::class)
        ->parameters(['locales' => 'local', 'servicios' => 'servicio'])
        ->shallow()
        ->except(['show']);
    Route::put('servicios/{servicio}/tamanos', [ServicioLocalController::class, 'sincronizarTamanos']);

    Route::apiResource('locales.productos', ProductoController::class)
        ->parameters(['locales' => 'local', 'productos' => 'producto'])
        ->shallow()
        ->except(['show']);

    // Sin edición ni borrado: una solicitud ya enviada no se retracta, y
    // aprobar/rechazar es tarea de soporte de plataforma (no hay endpoint
    // todavía, ver el servicio).
    Route::get('locales/{local}/solicitudes-catalogo', [SolicitudCatalogoController::class, 'index']);
    Route::post('locales/{local}/solicitudes-catalogo', [SolicitudCatalogoController::class, 'store']);
});
