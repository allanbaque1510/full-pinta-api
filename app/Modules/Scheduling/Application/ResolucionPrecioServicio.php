<?php

namespace App\Modules\Scheduling\Application;

use App\Models\ServicioLocal;

/**
 * Precio y duración de un `ServicioLocal` (§4.5, §4.7): planos por defecto,
 * o los de `servicio_local_tamano` cuando el servicio varía por tamaño de
 * mascota y la cita trae una. Usado tanto al calcular slots (`DisponibilidadService`)
 * como al congelar el `cita_item` (`CitaService`) — misma resolución en los
 * dos lugares, para que lo que se mostró sea lo que se cobra.
 *
 * Requiere `$servicio->tamanos` precargada cuando `$tamanoId` no es null.
 */
final readonly class ResolucionPrecioServicio
{
    /** @return array{precio: float, duracion_min: int} */
    public function resolver(ServicioLocal $servicio, ?string $tamanoId): array
    {
        if ($tamanoId !== null) {
            $porTamano = $servicio->tamanos->firstWhere('tamano_id', $tamanoId);

            if ($porTamano !== null) {
                return ['precio' => (float) $porTamano->precio, 'duracion_min' => $porTamano->duracion_min];
            }
        }

        return ['precio' => (float) $servicio->precio, 'duracion_min' => $servicio->duracion_min];
    }
}
