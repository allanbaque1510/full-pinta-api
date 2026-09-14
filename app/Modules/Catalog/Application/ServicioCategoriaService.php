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
     * @param  string|null  $verticalCodigo  código de `vertical` (p.ej. "barberia"), no su id.
     */
    public function listar(?string $verticalCodigo = null): Collection
    {
        return ServicioCategoria::query()
            ->join('vertical', 'vertical.id', '=', 'servicio_categoria.vertical_id')
            ->with('vertical')
            ->when($verticalCodigo, fn ($q) => $q->where('vertical.codigo', $verticalCodigo))
            ->where('servicio_categoria.activo', true)
            ->orderBy('vertical.codigo')
            ->orderBy('servicio_categoria.orden')
            ->select('servicio_categoria.*')
            ->get();
    }
}
