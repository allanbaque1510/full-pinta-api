<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\AnonimizarUsuario;
use App\Modules\Identity\Application\ConfirmarVerificacionEmail;
use App\Modules\Identity\Application\SolicitarVerificacionEmail;
use App\Modules\Identity\Http\Requests\ConfirmarVerificacionEmailRequest;
use App\Modules\Identity\Http\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autogestión de la cuenta: derecho de eliminación (§13.1) y verificación de
 * propiedad del email. El resto del perfil (foto, nombre) se gestiona por
 * Directory/Identity según corresponda cuando existan esas pantallas.
 */
class CuentaController extends Controller
{
    /**
     * Derecho de eliminación. No borra la fila — el historial de citas cuelga
     * de este `usuario_id` (§4.2) — la anonimiza y revoca sus tokens.
     */
    public function eliminar(Request $request, AnonimizarUsuario $anonimizar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $anonimizar) {
            $anonimizar($request->user());

            return response()->json(status: 204);
        });
    }

    public function solicitarVerificacionEmail(Request $request, SolicitarVerificacionEmail $solicitar): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $solicitar) {
            $solicitar($request->user());

            return [
                'mensaje' => 'Se envió un código a tu correo.',
                'expira_en_minutos' => config('fullpinta.otp.expira_minutos'),
            ];
        });
    }

    public function confirmarVerificacionEmail(
        ConfirmarVerificacionEmailRequest $request,
        ConfirmarVerificacionEmail $confirmar,
    ): JsonResponse {
        return $this->ejecutar(function () use ($request, $confirmar) {
            $confirmar($request->user(), $request->validated('codigo'));

            return UsuarioResource::make($request->user()->fresh());
        });
    }
}
