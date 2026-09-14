<?php

namespace App\Modules\Directory\Application;

use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Todo lo que se puede hacer con un `Negocio` (§4.4), agrupado en un solo
 * archivo para tener la lógica del modelo en un lugar y poder reusarla desde
 * cualquier controlador o job que la necesite — no una clase por acción.
 */
final readonly class NegocioService
{
    /**
     * Da de alta un negocio y provisiona la membresía de su propietario en el
     * mismo movimiento.
     *
     * `negocio.propietario_id` fija al dueño legal, pero las Policies y
     * `ContextoAcceso` (§3.2) solo miran `negocio_miembro` — es la única tabla
     * que consultan para resolver rol. Sin esta fila, el propio dueño no
     * podría crear su primer local: quedaría siendo propietario "de papel"
     * sin membresía que se lo autorice.
     */
    public function crear(Usuario $propietario, string $nombreMarca, ?string $ruc = null): Negocio
    {
        return DB::transaction(function () use ($propietario, $nombreMarca, $ruc) {
            $negocio = Negocio::create([
                'nombre_marca' => $nombreMarca,
                'ruc' => $ruc,
                'propietario_id' => $propietario->id,
                'plan_id' => Plan::where('codigo', 'free')->value('id'),
            ]);

            NegocioMiembro::create([
                'usuario_id' => $propietario->id,
                'negocio_id' => $negocio->id,
                'local_id' => null, // todos los locales del negocio
                'rol' => 'propietario',
                'desde' => now()->toDateString(),
            ]);

            return $negocio->load('plan');
        });
    }

    public function actualizar(Negocio $negocio, array $datos): Negocio
    {
        $negocio->update($datos);

        return $negocio->load('plan');
    }
}
