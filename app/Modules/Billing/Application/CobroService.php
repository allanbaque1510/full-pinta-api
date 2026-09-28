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

    /**
     * Revisión de base de datos 2026-09-28: sin guardia de estado, se podía
     * marcar pagado un cobro que ya estaba pagado — reemitiendo un
     * `comprobante_sri` duplicado para el mismo pago. Mismo criterio que ya
     * usa `LiquidacionService` en cada una de sus transiciones.
     */
    public function marcarPagado(Cobro $cobro): Cobro
    {
        if (! in_array($cobro->estado, ['pendiente', 'fallido'], true)) {
            throw_validacion("No se puede marcar pagado un cobro '{$cobro->estado}'.", 'estado');
        }

        $cobro->update([
            'estado' => 'pagado',
            'pagado_at' => now(),
            'comprobante_sri' => $this->sri->emitir($cobro),
        ]);

        return $cobro;
    }

    public function marcarFallido(Cobro $cobro): Cobro
    {
        if ($cobro->estado !== 'pendiente') {
            throw_validacion("No se puede marcar fallido un cobro '{$cobro->estado}'.", 'estado');
        }

        $cobro->increment('intentos');
        $cobro->update(['estado' => 'fallido']);

        return $cobro;
    }

    /**
     * `reembolsado` era un estado muerto: definido en el `CHECK` del esquema,
     * pero sin ningún método que transicionara a él (revisión de base de
     * datos, 2026-09-28).
     */
    public function marcarReembolsado(Cobro $cobro): Cobro
    {
        if ($cobro->estado !== 'pagado') {
            throw_validacion("Solo se puede reembolsar un cobro 'pagado'.", 'estado');
        }

        $cobro->update(['estado' => 'reembolsado']);

        return $cobro;
    }
}
