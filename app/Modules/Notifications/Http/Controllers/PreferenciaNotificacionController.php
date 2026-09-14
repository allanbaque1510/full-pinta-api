<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Application\PreferenciaNotificacionService;
use App\Modules\Notifications\Http\Requests\SincronizarPreferenciasRequest;
use App\Modules\Notifications\Http\Resources\PreferenciaNotificacionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenciaNotificacionController extends Controller
{
    public function index(Request $request, PreferenciaNotificacionService $preferencias): JsonResponse
    {
        return $this->ejecutar(
            fn () => PreferenciaNotificacionResource::collection($preferencias->listar($request->user())),
        );
    }

    public function update(SincronizarPreferenciasRequest $request, PreferenciaNotificacionService $preferencias): JsonResponse
    {
        return $this->ejecutar(fn () => PreferenciaNotificacionResource::collection(
            $preferencias->sincronizar($request->user(), $request->validated('preferencias')),
        ));
    }
}
