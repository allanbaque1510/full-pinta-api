<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\ProfesionalFoto;
use App\Modules\Staffing\Application\ProfesionalFotoService;
use App\Modules\Staffing\Http\Requests\ActualizarProfesionalFotoRequest;
use App\Modules\Staffing\Http\Requests\CrearProfesionalFotoRequest;
use App\Modules\Staffing\Http\Resources\ProfesionalFotoResource;
use Illuminate\Http\JsonResponse;

class ProfesionalFotoController extends Controller
{
    public function index(Profesional $profesional, ProfesionalFotoService $fotos): JsonResponse
    {
        return $this->ejecutar(function () use ($profesional, $fotos) {
            $this->authorize('ver', $profesional);

            return ProfesionalFotoResource::collection($fotos->listar($profesional));
        });
    }

    public function store(CrearProfesionalFotoRequest $request, Profesional $profesional, ProfesionalFotoService $fotos): JsonResponse
    {
        return $this->ejecutar(fn () => ProfesionalFotoResource::make($fotos->agregar($profesional, $request->validated())), 201);
    }

    // La ruta lleva {profesional} Y {foto} (no es `shallow`, ver routes.php):
    // ambos parámetros de la URI tienen que declararse en la firma, aunque
    // $profesional no se use adentro — si no, Laravel los pasa por posición
    // y el binding de $foto se corre al parámetro equivocado.
    public function update(ActualizarProfesionalFotoRequest $request, Profesional $profesional, ProfesionalFoto $foto, ProfesionalFotoService $fotos): JsonResponse
    {
        return $this->ejecutar(fn () => ProfesionalFotoResource::make($fotos->actualizar($foto, $request->validated())));
    }

    public function destroy(Profesional $profesional, ProfesionalFoto $foto, ProfesionalFotoService $fotos): JsonResponse
    {
        return $this->ejecutar(function () use ($foto, $fotos) {
            $this->authorize('actualizar', $foto->profesional);

            $fotos->eliminar($foto);

            return response()->json(status: 204);
        });
    }
}
