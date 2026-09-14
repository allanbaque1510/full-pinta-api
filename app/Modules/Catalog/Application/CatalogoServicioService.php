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
     * @param  string|null  $verticalCodigo  código de `vertical`, no su id.
     * @param  string|null  $categoriaCodigo  código de `servicio_categoria`, no su id.
     */
    public function listar(?string $verticalCodigo = null, ?string $categoriaCodigo = null): Collection
    {
        return CatalogoServicio::query()
            ->join('servicio_categoria', 'servicio_categoria.id', '=', 'catalogo_servicio.categoria_id')
            ->join('vertical', 'vertical.id', '=', 'servicio_categoria.vertical_id')
            ->with(['categoria.vertical', 'tipoRecurso'])
            ->when($verticalCodigo, fn ($q) => $q->where('vertical.codigo', $verticalCodigo))
            ->when($categoriaCodigo, fn ($q) => $q->where('servicio_categoria.codigo', $categoriaCodigo))
            ->where('catalogo_servicio.activo', true)
            ->orderBy('catalogo_servicio.nombre')
            ->select('catalogo_servicio.*')
            ->get();
    }
}
