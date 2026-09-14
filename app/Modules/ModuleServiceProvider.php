<?php

namespace App\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base de los proveedores de módulo.
 *
 * Cada módulo carga sus propias rutas y registra sus propios bindings. Los
 * puertos que un módulo expone a otros (§12.3) se enlazan aquí, en el módulo
 * dueño del dato — nunca en el que consume.
 *
 * Las MIGRACIONES no viven aquí: van todas en `database/migrations`. El esquema
 * no es modular aunque el código sí lo sea — `cita` tiene claves foráneas a
 * `local`, `usuario`, `profesional`, `recurso`, `servicio_local`, `producto` y
 * `mascota`. Es una sola base, una sola línea de tiempo. La frontera del §12.3
 * es sobre modelos Eloquent, no sobre DDL.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $rutas = $this->rutaBase().'/routes.php';

        if (is_file($rutas)) {
            Route::prefix('api/v1')
                ->middleware('api')
                ->group($rutas);
        }
    }

    protected function rutaBase(): string
    {
        return dirname((new \ReflectionClass($this))->getFileName());
    }
}
