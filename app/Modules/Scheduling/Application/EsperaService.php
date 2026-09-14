<?php

namespace App\Modules\Scheduling\Application;

use App\Models\Cita;
use App\Models\Espera;
use App\Models\Local;
use App\Models\Usuario;
use App\Modules\Scheduling\Events\EsperaNotificada;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lista de espera (§4.7): cuesta poco, retiene mucho y convierte
 * cancelaciones en citas — en barbería los sábados se llenan.
 *
 * No auto-reserva nada: el cliente sigue agendando por el camino normal
 * (`CitaService::reservar()`), esto solo decide a quién avisar primero
 * cuando se libera un cupo (para cuando exista el módulo Notifications) y
 * cierra el ciclo cuando esa persona sí reserva.
 */
final readonly class EsperaService
{
    public function listar(Local $local): Collection
    {
        return $local->esperas()->orderBy('fecha_deseada')->get();
    }

    /**
     * @param  array{servicio_local_id: string, fecha_deseada: string, profesional_id?: ?string, desde?: ?string, hasta?: ?string}  $datos
     */
    public function crear(Local $local, Usuario $cliente, array $datos): Espera
    {
        return Espera::create([
            'local_id' => $local->id,
            'cliente_id' => $cliente->id,
            'profesional_id' => $datos['profesional_id'] ?? null,
            'servicio_local_id' => $datos['servicio_local_id'],
            'fecha_deseada' => $datos['fecha_deseada'],
            'desde' => $datos['desde'] ?? null,
            'hasta' => $datos['hasta'] ?? null,
            'estado' => 'activa',
        ]);
    }

    /**
     * Un slot se acaba de liberar (cancelación, no-show o hold expirado): se
     * marca `notificada` a quien esperaba justo eso — mismo local, misma
     * fecha, mismo servicio, y el mismo profesional si pidió uno concreto.
     */
    public function buscarCandidatas(Cita $citaLiberada): void
    {
        $citaLiberada->loadMissing('items');
        $servicioIds = $citaLiberada->items->pluck('servicio_local_id');

        if ($servicioIds->isEmpty()) {
            return;
        }

        $candidatas = Espera::where('local_id', $citaLiberada->local_id)
            ->where('estado', 'activa')
            ->whereDate('fecha_deseada', $citaLiberada->inicio->toDateString())
            ->whereIn('servicio_local_id', $servicioIds)
            ->where(fn ($q) => $q->whereNull('profesional_id')->orWhere('profesional_id', $citaLiberada->profesional_id))
            ->get();

        foreach ($candidatas as $espera) {
            $espera->update(['estado' => 'notificada']);
            EsperaNotificada::dispatch($espera->id, $citaLiberada->id);
        }
    }

    /** El cliente reservó de verdad: si tenía una espera `notificada` para este local/fecha, se cierra el ciclo. */
    public function convertirSiCorresponde(Cita $citaNueva): void
    {
        Espera::where('local_id', $citaNueva->local_id)
            ->where('cliente_id', $citaNueva->cliente_id)
            ->where('estado', 'notificada')
            ->whereDate('fecha_deseada', $citaNueva->inicio->toDateString())
            ->update(['estado' => 'convertida']);
    }
}
