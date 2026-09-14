<?php

namespace App\Modules\Reviews\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reviews\Application\ReporteService;
use App\Modules\Reviews\Http\Requests\CrearReporteRequest;
use App\Modules\Reviews\Http\Resources\ReporteResource;
use Illuminate\Http\JsonResponse;

class ReporteController extends Controller
{
    public function store(CrearReporteRequest $request, ReporteService $reportes): JsonResponse
    {
        return $this->ejecutar(
            fn () => ReporteResource::make($reportes->crear($request->user(), $request->validated())),
            201,
        );
    }
}
