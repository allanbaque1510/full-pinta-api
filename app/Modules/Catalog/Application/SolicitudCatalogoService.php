<?php

namespace App\Modules\Catalog\Application;

use App\Models\Local;
use App\Models\Rubro;
use App\Models\SolicitudCatalogo;
use App\Models\Usuario;
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
        return $local->solicitudes()->with('rubro')->get();
    }

    /**
     * @param  array{rubro: string, nombre_propuesto: string, descripcion?: ?string}  $datos  `rubro` es el código, no el id.
     */
    public function crear(Local $local, Usuario $solicitante, array $datos): SolicitudCatalogo
    {
        $rubroId = Rubro::where('codigo', $datos['rubro'])->value('id');
        unset($datos['rubro']);

        return $local->solicitudes()->create([
            ...$datos,
            'solicitante_id' => $solicitante->id,
            'rubro_id' => $rubroId,
            'estado' => 'pendiente',
        ]);
    }
}
