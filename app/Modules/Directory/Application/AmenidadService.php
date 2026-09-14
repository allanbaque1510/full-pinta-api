<?php

namespace App\Modules\Directory\Application;

use App\Models\Amenidad;
use App\Models\Local;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `Amenidad` (§4.4): el catálogo lo define la
 * plataforma (lectura pública) y cada local elige cuáles tiene.
 */
final readonly class AmenidadService
{
    /**
     * @param  string|null  $categoriaCodigo  código de `amenidad_categoria`, no su id.
     */
    public function listar(?string $categoriaCodigo = null): Collection
    {
        return Amenidad::query()
            ->join('amenidad_categoria', 'amenidad_categoria.id', '=', 'amenidad.categoria_id')
            ->with('categoria')
            ->when($categoriaCodigo, fn ($q) => $q->where('amenidad_categoria.codigo', $categoriaCodigo))
            ->where('amenidad.activo', true)
            ->orderBy('amenidad_categoria.codigo')
            ->orderBy('amenidad.nombre')
            ->select('amenidad.*')
            ->get();
    }

    /**
     * Reemplaza el conjunto completo de amenidades del local — no agrega ni
     * quita una por una. El front manda la lista final que quiere que quede,
     * cada una con su `detalle` opcional ("cerveza artesanal", "PS5").
     *
     * @param  array<int,array{amenidad_id: string, detalle?: ?string}>  $amenidades
     */
    public function sincronizarEnLocal(Local $local, array $amenidades): Collection
    {
        $mapa = collect($amenidades)->mapWithKeys(
            fn (array $a) => [$a['amenidad_id'] => ['detalle' => $a['detalle'] ?? null]],
        )->all();

        $local->amenidades()->sync($mapa);

        return $local->amenidades()->with('categoria')->get();
    }
}
