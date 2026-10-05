<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\Profesional;
use App\Modules\Staffing\Application\ProfesionalService;
use App\Modules\Staffing\Http\Requests\ActualizarProfesionalRequest;
use App\Modules\Staffing\Http\Requests\CrearProfesionalRequest;
use App\Modules\Staffing\Http\Requests\VincularCuentaProfesionalRequest;
use App\Modules\Staffing\Http\Resources\ProfesionalResource;
use App\Support\Auth\ContextoAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfesionalController extends Controller
{
    public function index(Local $local, ProfesionalService $profesionales): JsonResponse
    {
        return $this->ejecutar(fn () => ProfesionalResource::collection($profesionales->listarPorLocal($local)));
    }

    public function store(CrearProfesionalRequest $request, Local $local, ProfesionalService $profesionales): JsonResponse
    {
        return $this->ejecutar(fn () => ProfesionalResource::make($profesionales->crearConAsignacion(
            $local,
            $request->safe()->only(['nombre', 'alias', 'bio', 'foto_url', 'perfil_publico', 'traslado_min', 'telefono']),
            $request->safe()->only(['rol', 'modalidad', 'comision_pct', 'desde']),
        )), 201);
    }

    public function show(Profesional $profesional): JsonResponse
    {
        return $this->ejecutar(fn () => ProfesionalResource::make($profesional->loadMissing('fotoPerfil')));
    }

    public function perfilPublico(Profesional $profesional, ProfesionalService $profesionales, Request $request): JsonResponse
    {
        // `perfilPublico()` ya devuelve el array resuelto por
        // `ProfesionalPublicoResource` (con `es_favorito` fusionado aparte —
        // ver el comentario en el servicio), así que aquí no se vuelve a
        // envolver.
        //
        // Ruta pública (sin `auth:sanctum`): `user('sanctum')` resuelve el
        // Bearer token si viene uno, sin exigirlo — a diferencia de
        // `$request->user()` a secas, que usaría el guard 'web' (sesión) y
        // nunca vería el token de un cliente API.
        return $this->ejecutar(fn () => $profesionales->perfilPublico($profesional, $request->user('sanctum')));
    }

    public function update(ActualizarProfesionalRequest $request, Profesional $profesional, ProfesionalService $profesionales): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $profesional, $profesionales) {
            // Editar su propia ficha (§3.2): quien administra alguno de sus
            // locales, o el propio profesional.
            $contexto = new ContextoAcceso($request->user());
            $autorizado = $contexto->tienePermisoSobreProfesional($profesional, 'profesionales.update')
                || $request->user()->profesional?->id === $profesional->id;

            abort_unless($autorizado, 403, 'No tienes permiso para esto.');

            return ProfesionalResource::make($profesionales->actualizar($profesional, $request->validated()));
        });
    }

    public function vincularCuenta(VincularCuentaProfesionalRequest $request, Profesional $profesional, ProfesionalService $profesionales): JsonResponse
    {
        return $this->ejecutar(fn () => ProfesionalResource::make(
            $profesionales->vincularCuenta($profesional, $request->validated('telefono')),
        ));
    }
}
