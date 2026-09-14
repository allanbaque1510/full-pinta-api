<?php

namespace App\Modules\Reviews\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Local;
use App\Models\Resena;
use App\Modules\Reviews\Application\ResenaService;
use App\Modules\Reviews\Http\Requests\CrearResenaRequest;
use App\Modules\Reviews\Http\Requests\ResponderResenaRequest;
use App\Modules\Reviews\Http\Resources\ResenaResource;
use Illuminate\Http\JsonResponse;

class ResenaController extends Controller
{
    public function store(CrearResenaRequest $request, Cita $cita, ResenaService $resenas): JsonResponse
    {
        return $this->ejecutar(fn () => ResenaResource::make($resenas->crear($cita, $request->validated())), 201);
    }

    public function index(Local $local, ResenaService $resenas): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $resenas) {
            $this->authorize('ver', $local);

            return ResenaResource::collection($resenas->listarPorLocal($local));
        });
    }

    public function responder(ResponderResenaRequest $request, Resena $resena, ResenaService $resenas): JsonResponse
    {
        return $this->ejecutar(
            fn () => ResenaResource::make($resenas->responder($resena, $request->validated('respuesta_local'))),
        );
    }
}
