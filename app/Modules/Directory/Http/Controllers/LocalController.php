<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\Negocio;
use App\Modules\Directory\Application\LocalService;
use App\Modules\Directory\Http\Requests\ActualizarLocalRequest;
use App\Modules\Directory\Http\Requests\CrearLocalRequest;
use App\Modules\Directory\Http\Resources\LocalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalController extends Controller
{
    public function index(Negocio $negocio, LocalService $locales): JsonResponse
    {
        return $this->ejecutar(function () use ($negocio, $locales) {
            $this->authorize('verLocales', $negocio);

            return LocalResource::collection($locales->listarPorNegocio($negocio));
        });
    }

    public function store(CrearLocalRequest $request, Negocio $negocio, LocalService $locales): JsonResponse
    {
        return $this->ejecutar(fn () => LocalResource::make($locales->crear($negocio, $request->validated())), 201);
    }

    public function show(Local $local): JsonResponse
    {
        return $this->ejecutar(function () use ($local) {
            $this->authorize('ver', $local);

            return LocalResource::make($local);
        });
    }

    public function perfilPublico(Local $local, LocalService $locales, Request $request): JsonResponse
    {
        // `perfilPublico()` ya devuelve el array resuelto por
        // `LocalPublicoResource` (necesario para poder cachearlo — ver el
        // comentario en `LocalService::perfilPublico()`), así que aquí no se
        // vuelve a envolver.
        //
        // Ruta pública (sin `auth:sanctum`): `user('sanctum')` resuelve el
        // Bearer token si viene uno, sin exigirlo — a diferencia de
        // `$request->user()` a secas, que usaría el guard 'web' (sesión) y
        // nunca vería el token de un cliente API.
        return $this->ejecutar(fn () => $locales->perfilPublico($local, $request->user('sanctum')));
    }

    public function update(ActualizarLocalRequest $request, Local $local, LocalService $locales): JsonResponse
    {
        return $this->ejecutar(fn () => LocalResource::make($locales->actualizar($local, $request->validated())));
    }

    public function activar(Local $local, LocalService $locales): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $locales) {
            $this->authorize('cambiarEstado', $local);

            return LocalResource::make($locales->activar($local));
        });
    }

    public function pausar(Local $local, LocalService $locales): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $locales) {
            $this->authorize('cambiarEstado', $local);

            return LocalResource::make($locales->pausar($local));
        });
    }
}
