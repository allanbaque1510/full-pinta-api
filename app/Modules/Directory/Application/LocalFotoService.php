<?php

namespace App\Modules\Directory\Application;

use App\Models\Local;
use App\Models\LocalFoto;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `LocalFoto` (§4.4). El portafolio del local
 * importa para el descubrimiento (la gente decide por las fotos), pero eso se
 * explota recién en la búsqueda pública de la Fase 7 — aquí solo se
 * administra.
 */
final readonly class LocalFotoService
{
    public function listar(Local $local): Collection
    {
        return $local->fotos()->orderBy('orden')->get();
    }

    public function agregar(Local $local, array $datos): LocalFoto
    {
        // `orden` es opcional en el request pero tiene DEFAULT 0 en
        // Postgres — ese default vive en la base, no en PHP: si se omite, el
        // modelo recién creado queda con `null` en memoria en vez de 0 hasta
        // que alguien lo recargue.
        return $local->fotos()->create(['orden' => 0, ...$datos]);
    }

    public function actualizar(LocalFoto $foto, array $datos): LocalFoto
    {
        $foto->update($datos);

        return $foto;
    }

    public function eliminar(LocalFoto $foto): void
    {
        // Tabla sin `activo`/`estado`: se borra de verdad (§4.2).
        $foto->delete();
    }
}
