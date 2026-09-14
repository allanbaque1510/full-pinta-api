<?php

namespace App\Modules\Billing\Application;

use App\Models\Cita;
use App\Models\Liquidacion;
use App\Models\Local;
use App\Models\Profesional;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Liquidación de comisiones (§4.10, §10.2) — el killer feature, y la única
 * excepción documentada a "camino optimista sin locks" (§5.3): "aquí sí es
 * plata". Sale completa del precio y la comisión ya CONGELADOS en
 * `cita_item`/`cita_producto` al agendar/atender (§4.7) — nada se recalcula
 * contra el precio actual del servicio.
 */
final readonly class LiquidacionService
{
    public function listarPorLocal(Local $local): Collection
    {
        return $local->liquidaciones()->orderByDesc('periodo_desde')->get();
    }

    /**
     * Genera o regenera el borrador de un periodo. Se puede llamar tantas
     * veces como haga falta mientras siga en `borrador` — cada corrida
     * recalcula desde cero, nunca acumula sobre el valor anterior.
     */
    public function generarBorrador(Local $local, Profesional $profesional, CarbonImmutable $desde, CarbonImmutable $hasta): Liquidacion
    {
        return DB::transaction(function () use ($local, $profesional, $desde, $hasta) {
            $clave = [
                'local_id' => $local->id,
                'profesional_id' => $profesional->id,
                'periodo_desde' => $desde->toDateString(),
                'periodo_hasta' => $hasta->toDateString(),
            ];

            $existente = Liquidacion::where($clave)->first();

            if ($existente !== null && $existente->estado !== 'borrador') {
                throw_validacion("Ya existe una liquidación '{$existente->estado}' para este periodo; no se puede regenerar.", 'estado');
            }

            $totales = $this->sumarPeriodo($local, $profesional, $desde, $hasta);

            return Liquidacion::updateOrCreate($clave, [...$totales, 'estado' => 'borrador']);
        });
    }

    /**
     * Solo desde `borrador`. Recalcula una última vez bajo el mismo lock —
     * por si algo cambió desde el último borrador generado — y recién ahí
     * pasa a `cerrada`. Una vez cerrada, ya no se puede regenerar.
     */
    public function cerrar(Liquidacion $liquidacion): Liquidacion
    {
        if ($liquidacion->estado !== 'borrador') {
            throw_validacion("Solo se puede cerrar una liquidación en 'borrador'.", 'estado');
        }

        $liquidacion->loadMissing(['local', 'profesional']);

        return DB::transaction(function () use ($liquidacion) {
            $totales = $this->sumarPeriodo(
                $liquidacion->local,
                $liquidacion->profesional,
                CarbonImmutable::parse($liquidacion->periodo_desde),
                CarbonImmutable::parse($liquidacion->periodo_hasta),
            );

            $liquidacion->update([...$totales, 'estado' => 'cerrada', 'cerrada_at' => now()]);

            return $liquidacion;
        });
    }

    public function marcarPagada(Liquidacion $liquidacion): Liquidacion
    {
        if ($liquidacion->estado !== 'cerrada') {
            throw_validacion("Solo se puede marcar pagada una liquidación 'cerrada'.", 'estado');
        }

        $liquidacion->update(['estado' => 'pagada']);

        return $liquidacion;
    }

    /**
     * @return array{total_servicios: float, total_productos: float, total_propinas: float,
     *     comision_servicios: float, comision_productos: float, total_a_pagar: float}
     */
    private function sumarPeriodo(Local $local, Profesional $profesional, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        // Lock del periodo (§5.3): bloquea estas citas mientras se suma, para
        // que un `CitaService::agregarProducto()` concurrente (que hace
        // `UPDATE` sobre la misma fila `cita`) espere a que esta transacción
        // termine, en vez de dejar un total a medio calcular.
        $citas = Cita::where('local_id', $local->id)
            ->where('profesional_id', $profesional->id)
            ->where('estado', 'completada')
            ->whereBetween('completada_at', [$desde, $hasta])
            ->with(['items', 'productos'])
            ->lockForUpdate()
            ->get();

        $totalServicios = 0.0;
        $totalProductos = 0.0;
        $totalPropinas = 0.0;
        $comisionServicios = 0.0;
        $comisionProductos = 0.0;

        foreach ($citas as $cita) {
            // 100% al profesional, nunca base de comisión (§4.7).
            $totalPropinas += (float) $cita->propina;

            foreach ($cita->items as $item) {
                $totalServicios += (float) $item->precio;

                if ($item->comisionable) {
                    $comisionServicios += (float) $item->precio * (float) $item->comision_pct / 100;
                }
            }

            foreach ($cita->productos as $producto) {
                $subtotal = (float) $producto->precio * $producto->cantidad;
                $totalProductos += $subtotal;
                $comisionProductos += $subtotal * (float) $producto->comision_pct / 100;
            }
        }

        return [
            'total_servicios' => $totalServicios,
            'total_productos' => $totalProductos,
            'total_propinas' => $totalPropinas,
            'comision_servicios' => $comisionServicios,
            'comision_productos' => $comisionProductos,
            'total_a_pagar' => $totalPropinas + $comisionServicios + $comisionProductos,
        ];
    }
}
