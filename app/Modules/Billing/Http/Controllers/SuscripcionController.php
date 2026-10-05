<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\Suscripcion;
use App\Modules\Billing\Application\SuscripcionService;
use App\Modules\Billing\Http\Requests\ActivarSuscripcionRequest;
use App\Modules\Billing\Http\Resources\SuscripcionResource;
use Illuminate\Http\JsonResponse;

class SuscripcionController extends Controller
{
    public function store(ActivarSuscripcionRequest $request, Negocio $negocio, SuscripcionService $suscripciones): JsonResponse
    {
        return $this->ejecutar(
            fn () => SuscripcionResource::make($suscripciones->activar($negocio, $request->validated())->load('plan')),
            201,
        );
    }

    public function show(Negocio $negocio): JsonResponse
    {
        return $this->ejecutar(function () use ($negocio) {
            abort_unless($negocio->propietario_id === request()->user()->id, 403, 'No tienes permiso para esto.');

            $suscripcion = $negocio->suscripciones()->with('plan')->latest()->firstOrFail();

            return SuscripcionResource::make($suscripcion);
        });
    }

    public function cancelar(Suscripcion $suscripcion, SuscripcionService $suscripciones): JsonResponse
    {
        return $this->ejecutar(function () use ($suscripcion, $suscripciones) {
            $suscripcion->loadMissing('negocio');
            abort_unless($suscripcion->negocio->propietario_id === request()->user()->id, 403, 'No tienes permiso para esto.');

            return SuscripcionResource::make($suscripciones->cancelar($suscripcion)->load('plan'));
        });
    }
}
