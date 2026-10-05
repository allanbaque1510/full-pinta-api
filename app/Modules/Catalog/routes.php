<?php

// Rutas del módulo Catalog (catálogo maestro, servicios del local, precios).
// Se montan bajo /api/v1 desde CatalogServiceProvider.
//
// Ver `docs/api-referencia.md` para el contrato completo request/response.
// Todas las rutas protegidas son permiso por rol (§3.2, ver
// `App\Http\Middleware\VerificarPermiso`) — ninguna depende de propiedad
// puntual acá.

use App\Modules\Catalog\Http\Controllers\CatalogoController;
use App\Modules\Catalog\Http\Controllers\ProductoController;
use App\Modules\Catalog\Http\Controllers\ServicioLocalController;
use App\Modules\Catalog\Http\Controllers\SolicitudCatalogoController;
use Illuminate\Support\Facades\Route;

// Público: el catálogo lo define la plataforma, no los locales (§4.5). El
// front lo necesita para armar el picker antes de crear un servicio_local.
Route::get('catalogo/categorias', [CatalogoController::class, 'categorias'])->name('catalogo.categorias');
Route::get('catalogo/servicios', [CatalogoController::class, 'servicios'])->name('catalogo.servicios');

Route::middleware(['auth:sanctum', 'permiso'])->group(function () {
    // Sin `show`: el listado siempre trae los servicios completos del local,
    // no hace falta consultar uno suelto por id. `destroy` desactiva
    // (`activo=false`, §4.2) en vez de borrar.
    Route::get('locales/{local}/servicios', [ServicioLocalController::class, 'index'])->name('locales.servicios.index');
    Route::post('locales/{local}/servicios', [ServicioLocalController::class, 'store'])->name('locales.servicios.store');
    Route::patch('servicios/{servicio}', [ServicioLocalController::class, 'update'])->name('servicios.update');
    Route::delete('servicios/{servicio}', [ServicioLocalController::class, 'destroy'])->name('servicios.destroy');
    Route::put('servicios/{servicio}/tamanos', [ServicioLocalController::class, 'sincronizarTamanos'])
        ->name('servicios.tamanos.update');

    Route::get('locales/{local}/productos', [ProductoController::class, 'index'])->name('locales.productos.index');
    Route::post('locales/{local}/productos', [ProductoController::class, 'store'])->name('locales.productos.store');
    Route::patch('productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    Route::delete('productos/{producto}', [ProductoController::class, 'destroy'])->name('productos.destroy');

    // Sin edición ni borrado: una solicitud ya enviada no se retracta, y
    // aprobar/rechazar es tarea de soporte de plataforma (no hay endpoint
    // todavía, ver el servicio).
    Route::get('locales/{local}/solicitudes-catalogo', [SolicitudCatalogoController::class, 'index'])
        ->name('locales.solicitudes-catalogo.index');
    Route::post('locales/{local}/solicitudes-catalogo', [SolicitudCatalogoController::class, 'store'])
        ->name('locales.solicitudes-catalogo.store');
});
