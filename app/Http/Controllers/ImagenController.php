<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarImagenRequest;
use App\Http\Requests\CrearImagenLocalRequest;
use App\Http\Requests\CrearImagenProfesionalRequest;
use App\Http\Resources\ImagenResource;
use App\Models\Imagen;
use App\Models\Local;
use App\Models\Profesional;
use App\Support\Auth\ContextoAcceso;
use App\Support\ImagenService;
use Illuminate\Http\JsonResponse;

/**
 * Galería polimórfica compartida (§4.4): reemplaza `LocalFotoController` y
 * `ProfesionalFotoController`, antes duplicados con el mismo shape. `index`/
 * `store` van por dueño (`locales/{local}/imagenes`,
 * `profesionales/{profesional}/imagenes` — URIs distintas, sin choque).
 * `update`/`destroy` son planas (`imagenes/{imagen}`) y se registran una sola
 * vez — dos `apiResource` distintas generando la misma URI se pisarían en
 * silencio, por eso vive fuera de cualquier módulo (ninguno es dueño único de
 * este dato) y las rutas planas se registran solo desde `Directory/routes.php`.
 */
class ImagenController extends Controller
{
    public function indexLocal(Local $local, ImagenService $imagenes): JsonResponse
    {
        return $this->ejecutar(fn () => ImagenResource::collection($imagenes->listar('local', $local->id)));
    }

    public function storeLocal(CrearImagenLocalRequest $request, Local $local, ImagenService $imagenes): JsonResponse
    {
        return $this->ejecutar(
            fn () => ImagenResource::make($imagenes->agregar('local', $local->id, $request->validated())),
            201,
        );
    }

    public function indexProfesional(Profesional $profesional, ImagenService $imagenes): JsonResponse
    {
        return $this->ejecutar(function () use ($profesional, $imagenes) {
            $contexto = new ContextoAcceso(request()->user());
            $autorizado = $contexto->tienePermisoSobreProfesional($profesional, 'profesionales.imagenes.index')
                || request()->user()->profesional?->id === $profesional->id;

            abort_unless($autorizado, 403, 'No tienes permiso para esto.');

            return ImagenResource::collection($imagenes->listar('profesional', $profesional->id));
        });
    }

    public function storeProfesional(CrearImagenProfesionalRequest $request, Profesional $profesional, ImagenService $imagenes): JsonResponse
    {
        return $this->ejecutar(
            fn () => ImagenResource::make($imagenes->agregar('profesional', $profesional->id, [
                ...$request->validated(),
                'tipo' => 'muestra',
            ])),
            201,
        );
    }

    public function update(ActualizarImagenRequest $request, Imagen $imagen, ImagenService $imagenes): JsonResponse
    {
        return $this->ejecutar(fn () => ImagenResource::make($imagenes->actualizar($imagen, $request->validated())));
    }

    public function destroy(Imagen $imagen, ImagenService $imagenes): JsonResponse
    {
        return $this->ejecutar(function () use ($imagen, $imagenes) {
            $dueno = $imagen->objeto;
            $contexto = new ContextoAcceso(request()->user());

            $autorizado = $dueno instanceof Local
                ? $contexto->tienePermiso($dueno, 'locales.imagenes.store')
                : $contexto->tienePermisoSobreProfesional($dueno, 'profesionales.imagenes.store')
                    || request()->user()->profesional?->id === $dueno->id;

            abort_unless($autorizado, 403, 'No tienes permiso para esto.');

            $imagenes->eliminar($imagen);

            return response()->json(status: 204);
        });
    }
}
