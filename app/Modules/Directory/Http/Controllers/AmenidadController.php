<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Directory\Application\AmenidadService;
use App\Modules\Directory\Http\Resources\AmenidadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de amenidades de la plataforma (§4.4). Público, sin autenticación.
 */
class AmenidadController extends Controller
{
    public function index(Request $request, AmenidadService $amenidades): JsonResponse
    {
        return $this->ejecutar(fn () => AmenidadResource::collection($amenidades->listar($request->query('categoria'))));
    }
}
