<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Directory\Application\BusquedaLocalService;
use App\Modules\Directory\Http\Requests\BuscarLocalesRequest;
use App\Modules\Directory\Http\Resources\BusquedaLocalResource;
use Illuminate\Http\JsonResponse;

/**
 * Búsqueda por cercanía (§7, §8). Público, sin autenticación.
 */
class BusquedaLocalController extends Controller
{
    public function index(BuscarLocalesRequest $request, BusquedaLocalService $busqueda): JsonResponse
    {
        return $this->ejecutar(fn () => BusquedaLocalResource::collection($busqueda->buscar($request->validated())));
    }
}
