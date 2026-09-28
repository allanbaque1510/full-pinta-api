<?php

namespace App\Modules\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un `servicio_local` se creó, editó o desactivó (§4.5). Directory lo
 * escucha para invalidar el caché del perfil público del local, que incluye
 * la lista de servicios (revisión de base de datos, 2026-09-28).
 */
final class ServicioLocalModificado
{
    use Dispatchable;

    public function __construct(
        public readonly string $localId,
    ) {}
}
