<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se agregó, actualizó o eliminó una fila de `imagen` (§4.4). Vive fuera de
 * cualquier módulo, igual que `App\Support\ImagenService` que lo dispara:
 * ningún módulo es dueño único de la galería polimórfica — la escuchan
 * quienes tengan algo que invalidar (p. ej. Directory, el caché del perfil
 * público del local).
 */
final readonly class ImagenModificada
{
    use Dispatchable;

    public function __construct(
        public string $objetoType,
        public string $objetoId,
    ) {}
}
