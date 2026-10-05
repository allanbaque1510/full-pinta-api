<?php

namespace App\Console\Commands;

use App\Models\Permiso;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * Recorre todas las rutas que usan el middleware `permiso` y crea en el
 * catálogo (`permiso`) la fila que falte para su nombre — nunca borra ni
 * reactiva una existente, y una fila nueva nace `activo = false` y sin
 * ningún rol en `rol_permiso`: declarar la ruta no le da acceso a nadie
 * todavía, eso se decide aparte (seeder o mantenedor).
 *
 * Correrlo después de agregar rutas nuevas, como una migración más:
 * `php artisan permisos:sincronizar`.
 */
class SincronizarPermisos extends Command
{
    protected $signature = 'permisos:sincronizar';

    protected $description = 'Crea en el catálogo de permisos las rutas nuevas que usan el middleware `permiso`.';

    public function handle(): int
    {
        $creados = 0;

        foreach (Route::getRoutes() as $ruta) {
            $nombre = $ruta->getName();

            if ($nombre === null || ! in_array('permiso', $ruta->gatherMiddleware(), true)) {
                continue;
            }

            $permiso = Permiso::firstOrCreate(
                ['codigo' => $nombre],
                ['nombre' => $nombre, 'descripcion' => null, 'activo' => false],
            );

            if ($permiso->wasRecentlyCreated) {
                $creados++;
                $this->line("  + {$nombre}");
            }
        }

        $this->info($creados > 0
            ? "{$creados} permiso(s) nuevo(s). Quedan 'activo = false' y sin rol — actívalos desde el seeder o el mantenedor."
            : 'Nada nuevo que sincronizar.');

        return self::SUCCESS;
    }
}
