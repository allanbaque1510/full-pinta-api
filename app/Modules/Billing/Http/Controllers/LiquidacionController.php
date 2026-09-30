<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Liquidacion;
use App\Models\Local;
use App\Models\Profesional;
use App\Modules\Billing\Application\LiquidacionService;
use App\Modules\Billing\Http\Requests\CrearLiquidacionRequest;
use App\Modules\Billing\Http\Resources\LiquidacionResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class LiquidacionController extends Controller
{
    public function index(Local $local, LiquidacionService $liquidaciones): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $liquidaciones) {
            $this->authorize('gestionar', [Liquidacion::class, $local]);

            $lista = $liquidaciones->listarPorLocal($local)->load('local.negocio.plan');

            return LiquidacionResource::collection($lista);
        });
    }

    public function indexProfesional(Profesional $profesional, LiquidacionService $liquidaciones): JsonResponse
    {
        return $this->ejecutar(function () use ($profesional, $liquidaciones) {
            $this->authorize('verPropias', [Liquidacion::class, $profesional]);

            return LiquidacionResource::collection($liquidaciones->listarPorProfesional($profesional));
        });
    }

    public function store(CrearLiquidacionRequest $request, Local $local, LiquidacionService $liquidaciones): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $local, $liquidaciones) {
            $profesional = Profesional::findOrFail($request->validated('profesional_id'));

            $liquidacion = $liquidaciones->generarBorrador(
                $local,
                $profesional,
                CarbonImmutable::parse($request->validated('periodo_desde')),
                CarbonImmutable::parse($request->validated('periodo_hasta')),
            );

            return LiquidacionResource::make($liquidacion->load('local.negocio.plan'));
        }, 201);
    }

    public function cerrar(Liquidacion $liquidacion, LiquidacionService $liquidaciones): JsonResponse
    {
        return $this->ejecutar(function () use ($liquidacion, $liquidaciones) {
            $this->authorize('actualizar', $liquidacion);

            return LiquidacionResource::make($liquidaciones->cerrar($liquidacion)->load('local.negocio.plan'));
        });
    }

    public function marcarPagada(Liquidacion $liquidacion, LiquidacionService $liquidaciones): JsonResponse
    {
        return $this->ejecutar(function () use ($liquidacion, $liquidaciones) {
            $this->authorize('actualizar', $liquidacion);

            return LiquidacionResource::make($liquidaciones->marcarPagada($liquidacion)->load('local.negocio.plan'));
        });
    }
}
