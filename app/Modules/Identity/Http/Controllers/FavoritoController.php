<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\FavoritoService;
use App\Modules\Identity\Http\Requests\AlternarFavoritoRequest;
use App\Modules\Identity\Http\Resources\FavoritoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoritoController extends Controller
{
    public function index(Request $request, FavoritoService $favoritos): JsonResponse
    {
        return $this->ejecutar(fn () => FavoritoResource::collection($favoritos->listar($request->user())));
    }

    public function alternar(AlternarFavoritoRequest $request, FavoritoService $favoritos): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $favoritos) {
            $resultado = $favoritos->alternar(
                $request->user(),
                $request->validated('local_id'),
                $request->validated('profesional_id'),
            );

            return [
                'agregado' => $resultado['agregado'],
                'favorito' => $resultado['favorito'] ? FavoritoResource::make($resultado['favorito']) : null,
            ];
        });
    }
}
