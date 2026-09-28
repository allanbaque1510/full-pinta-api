<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\CambiarContrasena;
use App\Modules\Identity\Application\RestablecerContrasena;
use App\Modules\Identity\Application\SolicitarRecuperacionContrasena;
use App\Modules\Identity\Http\Requests\CambiarContrasenaRequest;
use App\Modules\Identity\Http\Requests\RestablecerContrasenaRequest;
use App\Modules\Identity\Http\Requests\SolicitarRecuperacionContrasenaRequest;
use App\Modules\Identity\Http\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;

/**
 * Recuperar (sin sesión, por código) y cambiar (con sesión, con la actual) la
 * contraseña (§4.3). Recuperar admite dos canales — teléfono por WhatsApp o
 * correo — el mismo mecanismo de código de un solo uso que ya usa `otp`.
 */
class ContrasenaController extends Controller
{
    public function solicitarRecuperacion(
        SolicitarRecuperacionContrasenaRequest $request,
        SolicitarRecuperacionContrasena $solicitar,
    ): JsonResponse {
        return $this->ejecutar(function () use ($request, $solicitar) {
            $solicitar(
                $request->validated('canal'),
                $request->validated('telefono'),
                $request->validated('email'),
            );

            return [
                'mensaje' => 'Si los datos son válidos, se envió un código.',
                'expira_en_minutos' => config('fullpinta.otp.expira_minutos'),
            ];
        });
    }

    public function restablecer(RestablecerContrasenaRequest $request, RestablecerContrasena $restablecer): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $restablecer) {
            [$usuario, $token] = $restablecer(
                $request->validated('canal'),
                $request->validated('telefono'),
                $request->validated('email'),
                $request->validated('codigo'),
                $request->validated('password'),
            );

            return [
                'usuario' => UsuarioResource::make($usuario->loadMissing('fotoPerfil')),
                'token' => $token->plainTextToken,
            ];
        });
    }

    public function cambiar(CambiarContrasenaRequest $request, CambiarContrasena $cambiar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $cambiar) {
            $cambiar($request->user(), $request->validated('actual'), $request->validated('nueva'));

            return response()->json(status: 204);
        });
    }
}
