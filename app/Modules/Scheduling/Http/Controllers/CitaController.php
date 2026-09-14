<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Local;
use App\Models\Producto;
use App\Modules\Scheduling\Application\CitaService;
use App\Modules\Scheduling\Application\Transiciones\CancelarCita;
use App\Modules\Scheduling\Application\Transiciones\CompletarCita;
use App\Modules\Scheduling\Application\Transiciones\ConfirmarCita;
use App\Modules\Scheduling\Application\Transiciones\IniciarCita;
use App\Modules\Scheduling\Application\Transiciones\MarcarNoShow;
use App\Modules\Scheduling\Application\Transiciones\ReagendarCita;
use App\Modules\Scheduling\Http\Requests\AgregarProductoRequest;
use App\Modules\Scheduling\Http\Requests\CancelarCitaRequest;
use App\Modules\Scheduling\Http\Requests\CompletarCitaRequest;
use App\Modules\Scheduling\Http\Requests\ConfirmarCitaRequest;
use App\Modules\Scheduling\Http\Requests\CrearCitaRequest;
use App\Modules\Scheduling\Http\Requests\IniciarCitaRequest;
use App\Modules\Scheduling\Http\Requests\MarcarNoShowRequest;
use App\Modules\Scheduling\Http\Requests\ReagendarCitaRequest;
use App\Modules\Scheduling\Http\Requests\RegistrarWalkInRequest;
use App\Modules\Scheduling\Http\Resources\CitaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CitaController extends Controller
{
    public function index(Request $request, Local $local): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $local) {
            $this->authorize('ver', $local);

            $citas = $local->citas()
                ->with(['items', 'productos', 'cliente'])
                ->when($request->query('fecha'), fn ($q, $fecha) => $q->whereDate('inicio', $fecha))
                ->when($request->query('profesional_id'), fn ($q, $id) => $q->where('profesional_id', $id))
                ->when($request->query('estado'), fn ($q, $estado) => $q->where('estado', $estado))
                ->orderBy('inicio')
                ->get();

            return CitaResource::collection($citas);
        });
    }

    public function misCitas(Request $request): JsonResponse
    {
        return $this->ejecutar(function () use ($request) {
            $citas = $request->user()->citas()
                ->with(['items', 'productos', 'cliente'])
                ->when($request->query('estado'), fn ($q, $estado) => $q->where('estado', $estado))
                ->when($request->query('local_id'), fn ($q, $localId) => $q->where('local_id', $localId))
                ->orderByDesc('inicio')
                ->get();

            return CitaResource::collection($citas);
        });
    }

    public function show(Cita $cita): JsonResponse
    {
        return $this->ejecutar(function () use ($cita) {
            $this->authorize('ver', $cita);

            return CitaResource::make($cita->load(['items', 'productos', 'cliente']));
        });
    }

    public function store(CrearCitaRequest $request, Local $local, CitaService $citas): JsonResponse
    {
        return $this->ejecutar(
            fn () => CitaResource::make($citas->reservar($local, $request->user(), $request->validated())),
            201,
        );
    }

    public function walkIn(RegistrarWalkInRequest $request, Local $local, CitaService $citas): JsonResponse
    {
        return $this->ejecutar(
            fn () => CitaResource::make($citas->registrarWalkIn($local, $request->validated())),
            201,
        );
    }

    public function confirmar(ConfirmarCitaRequest $request, Cita $cita, ConfirmarCita $confirmar): JsonResponse
    {
        return $this->ejecutar(fn () => CitaResource::make($confirmar($cita, $request->user())));
    }

    public function iniciar(IniciarCitaRequest $request, Cita $cita, IniciarCita $iniciar): JsonResponse
    {
        return $this->ejecutar(fn () => CitaResource::make($iniciar($cita, $request->user())));
    }

    public function completar(CompletarCitaRequest $request, Cita $cita, CompletarCita $completar): JsonResponse
    {
        return $this->ejecutar(fn () => CitaResource::make(
            $completar($cita, $request->user(), $request->validated('propina')),
        ));
    }

    public function cancelar(CancelarCitaRequest $request, Cita $cita, CancelarCita $cancelar): JsonResponse
    {
        return $this->ejecutar(fn () => CitaResource::make(
            $cancelar($cita, $request->user(), $request->validated('motivo')),
        ));
    }

    public function noShow(MarcarNoShowRequest $request, Cita $cita, MarcarNoShow $marcarNoShow): JsonResponse
    {
        return $this->ejecutar(fn () => CitaResource::make($marcarNoShow($cita, $request->user())));
    }

    public function reagendar(ReagendarCitaRequest $request, Cita $cita, ReagendarCita $reagendar): JsonResponse
    {
        return $this->ejecutar(
            fn () => CitaResource::make($reagendar($cita, $request->user(), $request->validated())),
            201,
        );
    }

    public function agregarProducto(AgregarProductoRequest $request, Cita $cita, CitaService $citas): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $cita, $citas) {
            $producto = Producto::findOrFail($request->validated('producto_id'));

            return CitaResource::make($citas->agregarProducto($cita, $producto, $request->validated('cantidad') ?? 1));
        });
    }
}
