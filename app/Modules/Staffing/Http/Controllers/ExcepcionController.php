<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Excepcion;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Recurso;
use App\Modules\Staffing\Application\ExcepcionService;
use App\Modules\Staffing\Http\Requests\CrearExcepcionLocalRequest;
use App\Modules\Staffing\Http\Requests\CrearExcepcionProfesionalRequest;
use App\Modules\Staffing\Http\Requests\CrearExcepcionRecursoRequest;
use App\Modules\Staffing\Http\Resources\ExcepcionResource;
use App\Support\Auth\ContextoAcceso;
use Illuminate\Http\JsonResponse;

class ExcepcionController extends Controller
{
    public function indexLocal(Local $local, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(fn () => ExcepcionResource::collection($excepciones->listarDeLocal($local)));
    }

    public function storeLocal(CrearExcepcionLocalRequest $request, Local $local, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(
            fn () => ExcepcionResource::make($excepciones->crearParaLocal($local, $request->validated())),
            201,
        );
    }

    public function indexProfesional(Profesional $profesional, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(fn () => ExcepcionResource::collection($excepciones->listarDeProfesional($profesional)));
    }

    public function storeProfesional(CrearExcepcionProfesionalRequest $request, Profesional $profesional, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $profesional, $excepciones) {
            // Bloquear su propio horario (§3.2): quien administra alguno de
            // sus locales, o el propio profesional.
            $contexto = new ContextoAcceso(request()->user());
            $autorizado = $contexto->tienePermisoSobreProfesional($profesional, 'profesionales.excepciones.store')
                || request()->user()->profesional?->id === $profesional->id;

            abort_unless($autorizado, 403, 'No tienes permiso para esto.');

            return ExcepcionResource::make($excepciones->crearParaProfesional($profesional, $request->validated()));
        }, 201);
    }

    public function indexRecurso(Recurso $recurso, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(fn () => ExcepcionResource::collection($excepciones->listarDeRecurso($recurso)));
    }

    public function storeRecurso(CrearExcepcionRecursoRequest $request, Recurso $recurso, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(
            fn () => ExcepcionResource::make($excepciones->crearParaRecurso($recurso, $request->validated())),
            201,
        );
    }

    public function destroy(Excepcion $excepcion, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(function () use ($excepcion, $excepciones) {
            // Cada tipo de excepción se autoriza contra su propio dueño: un
            // `Profesional` no cuelga de un solo local (§4.6), así que su caso
            // no puede resolverse con un solo permiso sobre un local.
            $contexto = new ContextoAcceso(request()->user());

            $autorizado = match (true) {
                $excepcion->local_id !== null => $contexto->tienePermiso($excepcion->local, 'locales.excepciones.store'),
                $excepcion->profesional_id !== null => $contexto->tienePermisoSobreProfesional($excepcion->profesional, 'profesionales.excepciones.store')
                    || request()->user()->profesional?->id === $excepcion->profesional_id,
                default => $contexto->tienePermiso($excepcion->recurso->local, 'locales.recursos.excepciones.store'),
            };

            abort_unless($autorizado, 403, 'No tienes permiso para esto.');

            $excepciones->eliminar($excepcion);

            return response()->json(status: 204);
        });
    }
}
