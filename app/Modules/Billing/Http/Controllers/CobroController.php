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
            abort_unless($negocio->propietario_id === request()->user()->id, 403, 'No tienes permiso para esto.');

            return CobroResource::collection($cobros->listarPorNegocio($negocio));
        });
    }

    public function marcarPagado(Cobro $cobro, CobroService $cobros): JsonResponse
    {
        return $this->ejecutar(function () use ($cobro, $cobros) {
            $cobro->loadMissing('suscripcion.negocio');
            abort_unless($cobro->suscripcion->negocio->propietario_id === request()->user()->id, 403, 'No tienes permiso para esto.');

            return CobroResource::make($cobros->marcarPagado($cobro));
        });
    }

    public function marcarReembolsado(Cobro $cobro, CobroService $cobros): JsonResponse
    {
        return $this->ejecutar(function () use ($cobro, $cobros) {
            $cobro->loadMissing('suscripcion.negocio');
            abort_unless($cobro->suscripcion->negocio->propietario_id === request()->user()->id, 403, 'No tienes permiso para esto.');

            return CobroResource::make($cobros->marcarReembolsado($cobro));
        });
    }
}
