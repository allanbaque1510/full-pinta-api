<?php

namespace App\Modules\Catalog\Application;

use App\Models\CatalogoServicio;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `CatalogoServicio` (§4.5): de solo lectura
 * para el front por ahora — lo define la plataforma, no los locales.
 */
final readonly class CatalogoServicioService
{
    /**
     * @param  string|null  $rubroCodigo  código de `rubro`, no su id.
     * @param  string|null  $categoriaCodigo  código de `servicio_categoria`, no su id.
     */
    public function listar(?string $rubroCodigo = null, ?string $categoriaCodigo = null): Collection
    {
        return CatalogoServicio::query()
            ->join('servicio_categoria', 'servicio_categoria.id', '=', 'catalogo_servicio.categoria_id')
            ->join('rubro', 'rubro.id', '=', 'servicio_categoria.rubro_id')
            ->with(['categoria.rubro', 'tipoRecurso'])
            ->when($rubroCodigo, fn ($q) => $q->where('rubro.codigo', $rubroCodigo))
            ->when($categoriaCodigo, fn ($q) => $q->where('servicio_categoria.codigo', $categoriaCodigo))
            ->where('catalogo_servicio.activo', true)
            ->orderBy('catalogo_servicio.nombre')
            ->select('catalogo_servicio.*')
            ->get();
    }
}
