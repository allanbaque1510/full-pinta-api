<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Modules\Notifications\Application\DeviceTokenService;
use App\Modules\Notifications\Http\Requests\RegistrarDeviceTokenRequest;
use App\Modules\Notifications\Http\Resources\DeviceTokenResource;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function store(RegistrarDeviceTokenRequest $request, DeviceTokenService $tokens): JsonResponse
    {
        return $this->ejecutar(
            fn () => DeviceTokenResource::make($tokens->registrar($request->user(), $request->validated())),
            201,
        );
    }

    public function destroy(DeviceToken $deviceToken, DeviceTokenService $tokens): JsonResponse
    {
        return $this->ejecutar(function () use ($deviceToken, $tokens) {
            // Propiedad puntual: solo el dueño del dispositivo.
            abort_unless($deviceToken->usuario_id === request()->user()->id, 403, 'No tienes permiso para esto.');

            $tokens->eliminar($deviceToken);

            return response()->json(status: 204);
        });
    }
}
