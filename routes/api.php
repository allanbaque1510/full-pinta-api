<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Raíz de la API. El equivalente honesto a la pantalla de bienvenida: en vez de
 * pintar un HTML estático que se ve igual aunque todo esté roto, comprueba las
 * dependencias de las que el servicio depende y dice cuál falla.
 */
Route::get('/', function () {
    $checks = [
        'base_datos' => fn () => DB::selectOne('select 1 as ok')->ok === 1,
        'postgis' => fn () => (bool) DB::selectOne('select postgis_version() as v')->v,
        'btree_gist' => fn () => (bool) DB::selectOne(
            "select 1 as v from pg_extension where extname = 'btree_gist'"
        )?->v,
        'tipo_franja' => fn () => (bool) DB::selectOne(
            "select 1 as v from pg_type where typname = 'franja'"
        )?->v,
        'redis' => function () {
            Cache::put('_salud', '1', 5);

            return Cache::get('_salud') === '1';
        },
    ];

    $resultado = [];
    $sano = true;

    foreach ($checks as $nombre => $check) {
        try {
            $ok = $check();
        } catch (Throwable $e) {
            $ok = false;
            $resultado[$nombre.'_error'] = $e->getMessage();
        }

        $resultado[$nombre] = $ok ? 'ok' : 'FALLA';
        $sano = $sano && $ok;
    }

    return response()->json([
        'servicio' => config('app.name'),
        'version' => 'v1',
        'entorno' => config('app.env'),
        'estado' => $sano ? 'operativo' : 'degradado',
        'hora_utc' => now()->toIso8601String(),
        'dependencias' => $resultado,
    ], $sano ? 200 : 503);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
