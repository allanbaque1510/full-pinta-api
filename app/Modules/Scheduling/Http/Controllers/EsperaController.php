<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Modules\Scheduling\Application\EsperaService;
use App\Modules\Scheduling\Http\Requests\CrearEsperaRequest;
use App\Modules\Scheduling\Http\Resources\EsperaResource;
use Illuminate\Http\JsonResponse;

class EsperaController extends Controller
{
    public function index(Local $local, EsperaService $esperas): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $esperas) {
            $this->authorize('ver', $local);

            return EsperaResource::collection($esperas->listar($local));
        });
    }

    public function store(CrearEsperaRequest $request, Local $local, EsperaService $esperas): JsonResponse
    {
        return $this->ejecutar(
            fn () => EsperaResource::make($esperas->crear($local, $request->user(), $request->validated())),
            201,
        );
    }
}
