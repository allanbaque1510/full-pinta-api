<?php

namespace App\Modules\Directory\Listeners;

use App\Events\ImagenModificada;
use App\Models\Resena;
use App\Modules\Catalog\Events\ProductoModificado;
use App\Modules\Catalog\Events\ServicioLocalModificado;
use App\Modules\Directory\Application\LocalService;
use App\Modules\Reviews\Events\ResenaCreada;
use App\Modules\Reviews\Events\ResenaRespondida;

/**
 * Revisión de base de datos 2026-09-28: `LocalService::perfilPublico()`
 * cachea 1 h un perfil que incluye `servicios` (Catalog) y `resenas`
 * (Reviews), pero antes solo se invalidaba desde el propio `LocalService` —
 * Directory escucha a Catalog y Reviews, nunca al revés (§12.3), igual que
 * Scheduling escucha a Staffing.
 */
class InvalidarCachePerfilPublico
{
    public function __construct(private LocalService $locales) {}

    public function handleServicioLocalModificado(ServicioLocalModificado $event): void
    {
        $this->locales->invalidarPerfilPublico($event->localId);
    }

    public function handleProductoModificado(ProductoModificado $event): void
    {
        $this->locales->invalidarPerfilPublico($event->localId);
    }

    /** Solo importa cuando el dueño de la imagen es un `local` — la galería del profesional no se cachea. */
    public function handleImagenModificada(ImagenModificada $event): void
    {
        if ($event->objetoType === 'local') {
            $this->locales->invalidarPerfilPublico($event->objetoId);
        }
    }

    public function handleResenaCreada(ResenaCreada $event): void
    {
        $this->invalidarPorResena($event->resenaId);
    }

    public function handleResenaRespondida(ResenaRespondida $event): void
    {
        $this->invalidarPorResena($event->resenaId);
    }

    private function invalidarPorResena(string $resenaId): void
    {
        $localId = Resena::where('id', $resenaId)->value('local_id');

        if ($localId !== null) {
            $this->locales->invalidarPerfilPublico($localId);
        }
    }
}
