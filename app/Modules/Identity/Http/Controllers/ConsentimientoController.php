<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\ActualizarConsentimiento;
use App\Modules\Identity\Application\ListarConsentimientos;
use App\Modules\Identity\Http\Requests\ActualizarConsentimientoRequest;
use App\Modules\Identity\Http\Resources\ConsentimientoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión de consentimientos por finalidad (§13.1). Operar la cita
 * (`operacion_servicio`) no es lo mismo que recibir promociones (`marketing`):
 * el front pide y revoca cada finalidad por separado, nunca con un solo toque
 * de "aceptar todo".
 */
class ConsentimientoController extends Controller
{
    public function index(Request $request, ListarConsentimientos $listar): JsonResponse
    {
        return $this->ejecutar(fn () => ConsentimientoResource::collection($listar($request->user())));
    }

    public function store(ActualizarConsentimientoRequest $request, ActualizarConsentimiento $actualizar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $actualizar) {
            $resultado = $actualizar(
                $request->user(),
                $request->validated('finalidad'),
                $request->boolean('otorgado'),
                $request->ip(),
                $request->userAgent(),
            );

            return is_array($resultado) ? $resultado : ConsentimientoResource::make($resultado);
        });
    }
}
