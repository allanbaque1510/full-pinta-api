<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurarEloquent();
        $this->protegerProduccion();

        // La API nunca envuelve un solo recurso en {"data": ...}: todo
        // Resource se serializa "plano", sea que el controlador lo devuelva
        // vía `response()->json($resource)` o `$resource->response()`. Sin
        // esto, cuál de las dos formas se use cambia el shape de la
        // respuesta — un detalle que ya rompió un endpoint por descuido.
        JsonResource::withoutWrapping();
    }

    /**
     * Modo estricto fuera de producción (§12.8).
     *
     * "El enemigo real no es la escala: son los N+1. Una pantalla de búsqueda
     * que dispara 400 consultas duele con 50 usuarios, mucho antes de que la
     * arquitectura importe."
     *
     * `shouldBeStrict` activa tres protecciones a la vez:
     *
     * - **preventLazyLoading** — acceder a una relación no precargada lanza
     *   excepción. Es el que caza los N+1 en desarrollo, no en producción.
     * - **preventSilentlyDiscardingAttributes** — un `fill()` con una columna
     *   que no está en `$fillable` falla en vez de ignorarla en silencio. Sin
     *   esto, un campo mal escrito se pierde sin que nadie se entere.
     * - **preventAccessingMissingAttributes** — leer una columna que no se
     *   trajo del SELECT lanza excepción en vez de devolver null. Un `null`
     *   silencioso en `precio` o `comision_pct` es plata mal calculada.
     *
     * En producción queda desactivado: ahí se prefiere una consulta de más a
     * una pantalla caída.
     */
    private function configurarEloquent(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * `migrate:fresh`, `db:wipe` y compañía quedan prohibidos en producción.
     * El comando existe y se teclea rápido; el respaldo tarda más.
     */
    private function protegerProduccion(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
