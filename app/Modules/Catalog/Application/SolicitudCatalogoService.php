<?php

namespace App\Modules\Catalog\Application;

use App\Models\Local;
use App\Models\SolicitudCatalogo;
use App\Models\Vertical;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cómo un local pide que la plataforma agregue un servicio que falta (§4.5).
 *
 * No hay `aprobar`/`rechazar` todavía: eso lo haría un panel de soporte de
 * plataforma, que no existe como concepto de autenticación en el proyecto
 * (ver `context/plan-implementacion.md`). Por ahora estas solicitudes se
 * revisan y se aprueban a mano, directo en la base.
 */
final readonly class SolicitudCatalogoService
{
    public function listar(Local $local): Collection
    {
        return $local->solicitudes()->with('vertical')->get();
    }

    /**
     * @param  array{vertical: string, nombre_propuesto: string, descripcion?: ?string}  $datos  `vertical` es el código, no el id.
     */
    public function crear(Local $local, array $datos): SolicitudCatalogo
    {
        $verticalId = Vertical::where('codigo', $datos['vertical'])->value('id');
        unset($datos['vertical']);

        return $local->solicitudes()->create([
            ...$datos,
            'vertical_id' => $verticalId,
            'estado' => 'pendiente',
        ]);
    }
}
