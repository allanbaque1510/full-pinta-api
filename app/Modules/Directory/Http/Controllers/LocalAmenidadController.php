<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Modules\Directory\Application\AmenidadService;
use App\Modules\Directory\Http\Requests\SincronizarAmenidadesRequest;
use App\Modules\Directory\Http\Resources\AmenidadResource;
use Illuminate\Http\JsonResponse;

class LocalAmenidadController extends Controller
{
    public function index(Local $local): JsonResponse
    {
        return $this->ejecutar(function () use ($local) {
            $this->authorize('ver', $local);

            return AmenidadResource::collection($local->amenidades()->with('categoria')->get());
        });
    }

    public function update(SincronizarAmenidadesRequest $request, Local $local, AmenidadService $amenidades): JsonResponse
    {
        return $this->ejecutar(fn () => AmenidadResource::collection(
            $amenidades->sincronizarEnLocal($local, $request->validated('amenidades')),
        ));
    }
}
