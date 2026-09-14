<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\CatalogoServicioService;
use App\Modules\Catalog\Application\ServicioCategoriaService;
use App\Modules\Catalog\Http\Resources\CatalogoServicioResource;
use App\Modules\Catalog\Http\Resources\ServicioCategoriaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo maestro (§4.5): lo define la plataforma, no los locales. Público,
 * sin autenticación — el front lo necesita antes de que exista ninguna cuenta.
 */
class CatalogoController extends Controller
{
    public function categorias(Request $request, ServicioCategoriaService $categorias): JsonResponse
    {
        return $this->ejecutar(fn () => ServicioCategoriaResource::collection(
            $categorias->listar($request->query('vertical')),
        ));
    }

    public function servicios(Request $request, CatalogoServicioService $servicios): JsonResponse
    {
        return $this->ejecutar(fn () => CatalogoServicioResource::collection(
            $servicios->listar($request->query('vertical'), $request->query('categoria')),
        ));
    }
}
