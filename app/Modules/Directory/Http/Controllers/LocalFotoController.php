<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\LocalFoto;
use App\Modules\Directory\Application\LocalFotoService;
use App\Modules\Directory\Http\Requests\ActualizarLocalFotoRequest;
use App\Modules\Directory\Http\Requests\CrearLocalFotoRequest;
use App\Modules\Directory\Http\Resources\LocalFotoResource;
use Illuminate\Http\JsonResponse;

class LocalFotoController extends Controller
{
    public function index(Local $local, LocalFotoService $fotos): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $fotos) {
            $this->authorize('ver', $local);

            return LocalFotoResource::collection($fotos->listar($local));
        });
    }

    public function store(CrearLocalFotoRequest $request, Local $local, LocalFotoService $fotos): JsonResponse
    {
        return $this->ejecutar(fn () => LocalFotoResource::make($fotos->agregar($local, $request->validated())), 201);
    }

    public function update(ActualizarLocalFotoRequest $request, LocalFoto $foto, LocalFotoService $fotos): JsonResponse
    {
        return $this->ejecutar(fn () => LocalFotoResource::make($fotos->actualizar($foto, $request->validated())));
    }

    public function destroy(LocalFoto $foto, LocalFotoService $fotos): JsonResponse
    {
        return $this->ejecutar(function () use ($foto, $fotos) {
            $this->authorize('gestionarCatalogo', $foto->local);

            $fotos->eliminar($foto);

            return response()->json(status: 204);
        });
    }
}
