<?php

namespace App\Modules\Staffing\Application;

use App\Models\Local;
use App\Models\Recurso;
use App\Models\TipoRecurso;
use Illuminate\Database\Eloquent\Collection;

/**
 * `Recurso` (§4.6): una fila por unidad física — "Silla 3", "Mesa 1" — nunca
 * una fila con `cantidad = 3`. Con un campo cantidad el constraint de
 * exclusión de citas subvende o, si se relaja, sobrevende.
 */
final readonly class RecursoService
{
    public function listar(Local $local): Collection
    {
        return $local->recursos()->with('tipoRecurso')->get();
    }

    /**
     * @param  array{tipo: string, nombre: string}  $datos  `tipo` es el código de `tipo_recurso`, no su id.
     */
    public function crear(Local $local, array $datos): Recurso
    {
        // `activo` no lo manda el cliente: nace activo.
        $recurso = $local->recursos()->create([
            'tipo_recurso_id' => TipoRecurso::where('codigo', $datos['tipo'])->value('id'),
            'nombre' => $datos['nombre'],
            'activo' => true,
        ]);

        return $recurso->load('tipoRecurso');
    }

    public function actualizar(Recurso $recurso, array $datos): Recurso
    {
        if (isset($datos['tipo'])) {
            $datos['tipo_recurso_id'] = TipoRecurso::where('codigo', $datos['tipo'])->value('id');
            unset($datos['tipo']);
        }

        $recurso->update($datos);

        return $recurso->load('tipoRecurso');
    }

    /** No se borra: se desactiva. Marcarlo fuera de servicio no afecta las otras unidades. */
    public function desactivar(Recurso $recurso): void
    {
        $recurso->update(['activo' => false]);
    }
}
