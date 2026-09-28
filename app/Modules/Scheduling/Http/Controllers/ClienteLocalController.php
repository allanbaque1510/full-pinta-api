<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\ClienteLocalService;
use App\Modules\Scheduling\Http\Requests\ActualizarClienteLocalRequest;
use App\Modules\Scheduling\Http\Requests\BandejaClientesLocalRequest;
use App\Modules\Scheduling\Http\Resources\ClienteLocalBandejaResource;
use App\Modules\Scheduling\Http\Resources\ClienteLocalResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Ficha del cliente en un local (§4.7): continuidad de servicio (`nota`,
 * `profesional_preferido`), no una calificación. Mismo permiso que ver la
 * agenda completa del local (propietario, admin, recepción — §3.2).
 */
class ClienteLocalController extends Controller
{
    public function index(BandejaClientesLocalRequest $request, Local $local, ClienteLocalService $clientes): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $local, $clientes) {
            $this->authorize('gestionarClientes', $local);

            $mes = $request->validated('mes') !== null
                ? CarbonImmutable::createFromFormat('Y-m', $request->validated('mes'))
                : CarbonImmutable::now();

            return ClienteLocalBandejaResource::collection($clientes->bandejaMensual($local, $mes));
        });
    }

    public function show(Local $local, Usuario $usuario, ClienteLocalService $clientes): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $usuario, $clientes) {
            $this->authorize('gestionarClientes', $local);

            return ClienteLocalResource::make($clientes->ficha($local, $usuario));
        });
    }

    public function update(ActualizarClienteLocalRequest $request, Local $local, Usuario $usuario, ClienteLocalService $clientes): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $local, $usuario, $clientes) {
            $this->authorize('gestionarClientes', $local);

            $clientes->actualizar($local, $usuario, $request->validated());

            return ClienteLocalResource::make($clientes->ficha($local, $usuario));
        });
    }
}
