<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\AnonimizarUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autogestión de la cuenta. Hoy solo el derecho de eliminación (§13.1); el
 * resto del perfil (foto, nombre) se gestiona por Directory/Identity según
 * corresponda cuando existan esas pantallas.
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
}
