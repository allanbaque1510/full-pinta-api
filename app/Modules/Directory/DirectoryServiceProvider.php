<?php

namespace App\Modules\Directory;

use App\Events\ImagenModificada;
use App\Modules\Catalog\Events\ProductoModificado;
use App\Modules\Catalog\Events\ServicioLocalModificado;
use App\Modules\Directory\Listeners\InvalidarCachePerfilPublico;
use App\Modules\ModuleServiceProvider;
use App\Modules\Reviews\Events\ResenaCreada;
use App\Modules\Reviews\Events\ResenaRespondida;
use Illuminate\Support\Facades\Event;

class DirectoryServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Directory escucha a Catalog, Reviews y a `App\Support\ImagenService`
        // (sin módulo dueño), nunca al revés (§12.3).
        Event::listen(ServicioLocalModificado::class, [InvalidarCachePerfilPublico::class, 'handleServicioLocalModificado']);
        Event::listen(ProductoModificado::class, [InvalidarCachePerfilPublico::class, 'handleProductoModificado']);
        Event::listen(ResenaCreada::class, [InvalidarCachePerfilPublico::class, 'handleResenaCreada']);
        Event::listen(ResenaRespondida::class, [InvalidarCachePerfilPublico::class, 'handleResenaRespondida']);
        Event::listen(ImagenModificada::class, [InvalidarCachePerfilPublico::class, 'handleImagenModificada']);
    }
}
