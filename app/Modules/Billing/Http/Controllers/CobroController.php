<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cobro;
use App\Models\Negocio;
use App\Modules\Billing\Application\CobroService;
use App\Modules\Billing\Http\Resources\CobroResource;
use Illuminate\Http\JsonResponse;

class CobroController extends Controller
{
    public function index(Negocio $negocio, CobroService $cobros): JsonResponse
    {
        return $this->ejecutar(function () use ($negocio, $cobros) {
            $this->authorize('gestionarSuscripcion', $negocio);

            return CobroResource::collection($cobros->listarPorNegocio($negocio));
        });
    }

    public function marcarPagado(Cobro $cobro, CobroService $cobros): JsonResponse
    {
        return $this->ejecutar(function () use ($cobro, $cobros) {
            $cobro->loadMissing('suscripcion.negocio');
            $this->authorize('gestionarSuscripcion', $cobro->suscripcion->negocio);

            return CobroResource::make($cobros->marcarPagado($cobro));
        });
    }
}
