<?php

namespace App\Modules\Billing\Application;

use App\Models\Cobro;
use App\Models\Negocio;
use App\Models\Suscripcion;
use App\Modules\Billing\Application\Contracts\EmisorComprobanteSri;
use Illuminate\Database\Eloquent\Collection;

/**
 * Registro de cobros (§10.1): la v1 no enruta dinero por la plataforma, así
 * que no hay pasarela detrás de esto — es el registro de que un cobro (hecho
 * por fuera, transferencia o el medio que sea) ocurrió, para que la
 * suscripción y la facturación electrónica cuadren.
 */
final readonly class CobroService
{
    public function __construct(private SuscripcionService $suscripciones, private EmisorComprobanteSri $sri) {}

    public function listarPorNegocio(Negocio $negocio): Collection
    {
        return Cobro::whereIn('suscripcion_id', $negocio->suscripciones()->pluck('id'))
            ->orderByDesc('created_at')
            ->get();
    }

    public function registrar(Suscripcion $suscripcion): Cobro
    {
        return Cobro::create([
            'suscripcion_id' => $suscripcion->id,
            'monto' => $this->suscripciones->montoDelCiclo($suscripcion),
            'estado' => 'pendiente',
            'intentos' => 0,
        ]);
    }

    public function marcarPagado(Cobro $cobro): Cobro
    {
        $cobro->update([
            'estado' => 'pagado',
            'pagado_at' => now(),
            'comprobante_sri' => $this->sri->emitir($cobro),
        ]);

        return $cobro;
    }

    public function marcarFallido(Cobro $cobro): Cobro
    {
        $cobro->increment('intentos');
        $cobro->update(['estado' => 'fallido']);

        return $cobro;
    }
}
