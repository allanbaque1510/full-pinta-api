<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\CerrarSesion;
use App\Modules\Identity\Application\IniciarSesionConEmail;
use App\Modules\Identity\Application\IniciarSesionConGoogle;
use App\Modules\Identity\Application\RegistrarConEmail;
use App\Modules\Identity\Application\ResolverContexto;
use App\Modules\Identity\Application\SolicitarOtp;
use App\Modules\Identity\Application\VerificarOtp;
use App\Modules\Identity\Http\Requests\GoogleLoginRequest;
use App\Modules\Identity\Http\Requests\LoginConEmailRequest;
use App\Modules\Identity\Http\Requests\RegistrarConEmailRequest;
use App\Modules\Identity\Http\Requests\SolicitarOtpRequest;
use App\Modules\Identity\Http\Requests\VerificarOtpRequest;
use App\Modules\Identity\Http\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autenticación (§4.3): teléfono + OTP, Google, y correo/contraseña. El
 * teléfono es obligatorio para los tres — Google y correo lo piden en el
 * mismo formulario de registro — y se verifica siempre con el mismo flujo de
 * OTP (`solicitarOtp`/`verificarOtp`), venga la cuenta de donde venga.
 *
 * Cada acción llama a UN solo servicio (`Application/`); el controlador solo
 * arma la forma HTTP de la respuesta (Resource, status) y deja que
 * `EjecutaServicio` traduzca cualquier excepción.
 */
class AuthController extends Controller
{
    public function solicitarOtp(SolicitarOtpRequest $request, SolicitarOtp $solicitar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $solicitar) {
            $solicitar($request->validated('telefono'));

            return [
                'mensaje' => 'Si el número es válido, se envió un código.',
                'expira_en_minutos' => config('fullpinta.otp.expira_minutos'),
            ];
        });
    }

    public function verificarOtp(VerificarOtpRequest $request, VerificarOtp $verificar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $verificar) {
            [$usuario, $token] = $verificar(
                $request->validated('telefono'),
                $request->validated('codigo'),
                $request->validated('nombre'),
            );

            return [
                'usuario' => UsuarioResource::make($usuario),
                'token' => $token->plainTextToken,
            ];
        }, 201);
    }

    public function google(GoogleLoginRequest $request, IniciarSesionConGoogle $iniciar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $iniciar) {
            [$usuario, $token] = $iniciar($request->validated('id_token'), $request->validated('telefono'));

            return [
                'usuario' => UsuarioResource::make($usuario),
                'token' => $token->plainTextToken,
            ];
        }, 201);
    }

    public function registrarConEmail(RegistrarConEmailRequest $request, RegistrarConEmail $registrar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $registrar) {
            [$usuario, $token] = $registrar($request->validated());

            return [
                'usuario' => UsuarioResource::make($usuario),
                'token' => $token->plainTextToken,
            ];
        }, 201);
    }

    public function loginConEmail(LoginConEmailRequest $request, IniciarSesionConEmail $iniciar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $iniciar) {
            [$usuario, $token] = $iniciar($request->validated('email'), $request->validated('password'));

            return [
                'usuario' => UsuarioResource::make($usuario),
                'token' => $token->plainTextToken,
            ];
        });
    }

    public function cerrarSesion(Request $request, CerrarSesion $cerrar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $cerrar) {
            $cerrar($request->user()->currentAccessToken());

            return response()->json(status: 204);
        });
    }

    public function contexto(Request $request, ResolverContexto $resolver): JsonResponse
    {
        return $this->ejecutar(fn () => $resolver($request->user()));
    }
}
