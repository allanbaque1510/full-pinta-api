<?php

namespace App\Modules\Catalog\Application;

use App\Models\ServicioCategoria;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `ServicioCategoria` (§4.5): de solo lectura
 * para el front por ahora — el catálogo lo define la plataforma, no hay
 * pantalla que permita a un local crear categorías.
 */
final readonly class ServicioCategoriaService
{
    /**
     * @param  string|null  $rubroCodigo  código de `rubro` (p.ej. "barberia"), no su id.
     */
    public function listar(?string $rubroCodigo = null): Collection
    {
        return ServicioCategoria::query()
            ->join('rubro', 'rubro.id', '=', 'servicio_categoria.rubro_id')
            ->with('rubro')
            ->when($rubroCodigo, fn ($q) => $q->where('rubro.codigo', $rubroCodigo))
            ->where('servicio_categoria.activo', true)
            ->orderBy('rubro.codigo')
            ->orderBy('servicio_categoria.orden')
            ->select('servicio_categoria.*')
            ->get();
    }
}
