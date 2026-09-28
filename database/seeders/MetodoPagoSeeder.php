<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

/**
 * Métodos de pago (§4.4): compartido entre `local_metodo_pago` (los que
 * acepta cada local) y `cita` (con cuál se pagó). Antes era la categoría
 * 'pago' de `amenidad` y un varchar+CHECK en `cita`, repetidos.
 */
class MetodoPagoSeeder extends Seeder
{
    /**
     * codigo => nombre, en orden de presentación.
     *
     * @var array<string, string>
     */
    private const METODOS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
        'payphone' => 'Payphone',
    ];

    public function run(): void
    {
        $orden = 0;

        foreach (self::METODOS as $codigo => $nombre) {
            MetodoPago::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'orden' => ++$orden, 'activo' => true],
            );
        }
    }
}
