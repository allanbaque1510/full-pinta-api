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
use Illuminate\Http\JsonResponse;

class ExcepcionController extends Controller
{
    public function indexLocal(Local $local, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $excepciones) {
            $this->authorize('ver', $local);

            return ExcepcionResource::collection($excepciones->listarDeLocal($local));
        });
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
        return $this->ejecutar(function () use ($profesional, $excepciones) {
            $this->authorize('ver', $profesional);

            return ExcepcionResource::collection($excepciones->listarDeProfesional($profesional));
        });
    }

    public function storeProfesional(CrearExcepcionProfesionalRequest $request, Profesional $profesional, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(
            fn () => ExcepcionResource::make($excepciones->crearParaProfesional($profesional, $request->validated())),
            201,
        );
    }

    public function indexRecurso(Recurso $recurso, ExcepcionService $excepciones): JsonResponse
    {
        return $this->ejecutar(function () use ($recurso, $excepciones) {
            $this->authorize('ver', $recurso->local);

            return ExcepcionResource::collection($excepciones->listarDeRecurso($recurso));
        });
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
            // no puede resolverse con `gestionarCatalogo` sobre un local.
            match (true) {
                $excepcion->local_id !== null => $this->authorize('gestionarCatalogo', $excepcion->local),
                $excepcion->profesional_id !== null => $this->authorize('actualizar', $excepcion->profesional),
                default => $this->authorize('gestionarCatalogo', $excepcion->recurso->local),
            };

            $excepciones->eliminar($excepcion);

            return response()->json(status: 204);
        });
    }
}
