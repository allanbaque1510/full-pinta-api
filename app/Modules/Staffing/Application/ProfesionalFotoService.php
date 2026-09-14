<?php

namespace App\Modules\Staffing\Application;

use App\Models\Profesional;
use App\Models\ProfesionalFoto;
use Illuminate\Database\Eloquent\Collection;

/**
 * El portafolio del profesional (§4.6) — probablemente el mejor mecanismo de
 * descubrimiento del producto: la gente escoge barbero viendo cortes, no
 * leyendo precios.
 */
final readonly class ProfesionalFotoService
{
    public function listar(Profesional $profesional): Collection
    {
        return $profesional->fotos()->orderBy('orden')->get();
    }

    public function agregar(Profesional $profesional, array $datos): ProfesionalFoto
    {
        // `orden` opcional en el request, DEFAULT 0 en Postgres.
        return $profesional->fotos()->create(['orden' => 0, ...$datos]);
    }

    public function actualizar(ProfesionalFoto $foto, array $datos): ProfesionalFoto
    {
        $foto->update($datos);

        return $foto;
    }

    public function eliminar(ProfesionalFoto $foto): void
    {
        $foto->delete();
    }
}
