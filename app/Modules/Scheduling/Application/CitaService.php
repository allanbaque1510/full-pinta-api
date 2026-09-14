<?php

namespace App\Modules\Scheduling\Application;

use App\Models\Asignacion;
use App\Models\Cita;
use App\Models\ClienteLocal;
use App\Models\ClientePerfil;
use App\Models\Local;
use App\Models\Producto;
use App\Models\ServicioLocal;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\Exceptions\SlotYaOcupado;
use App\Modules\Scheduling\Events\CitaCreada;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `Cita` (§4.7, §6): crear (hold, walk-in) y agregar productos. Las
 * transiciones de estado (confirmar, cancelar, completar...) viven en
 * `Application/Transiciones/`, una clase por transición.
 */
final readonly class CitaService
{
    /** SQLSTATE de violación de un constraint EXCLUDE. */
    private const EXCLUSION_VIOLATION = '23P01';

    /**
     * Camino normal desde la app: hold en `reservada` con `expira_at = +10min`
     * (§5.4) — salvo que el cliente tenga `requiere_confirmacion` (3er
     * no-show, §5.6), en cuyo caso nace `confirmada` directo: no se le da el
     * respiro de un hold sin confirmar.
     *
     * @param  array{profesional_id: string, servicios: array<int,string>, inicio: string, recurso_id?: ?string, para_tipo?: string, para_nombre?: ?string, mascota_id?: ?string, nota_cliente?: ?string}  $datos
     */
    public function reservar(Local $local, Usuario $cliente, array $datos): Cita
    {
        return $this->crear($local, $cliente, $datos, canal: 'app');
    }

    /**
     * Walk-in desde recepción (§4.7, §5.6): ocupa slot igual que uno de la
     * app, pero nace `confirmada` directo (el cliente ya está ahí, no hace
     * falta que confirme un hold) y con `canal = 'local'`.
     *
     * @param  array<string,mixed>  $datos  Igual que `reservar()`, más `cliente_id` O `nombre`+`telefono`.
     */
    public function registrarWalkIn(Local $local, array $datos): Cita
    {
        $cliente = $this->resolverClienteWalkIn($datos);

        return $this->crear($local, $cliente, $datos, canal: 'local', confirmadaDirecto: true);
    }

    /**
     * Suma un producto a una cita ya agendada (pomada, cera, shampoo...):
     * precio y comisión **congelados** desde `Producto`, no desde un join al
     * momento de liquidar (§4.7, §4.10).
     */
    public function agregarProducto(Cita $cita, Producto $producto, int $cantidad): Cita
    {
        DB::transaction(function () use ($cita, $producto, $cantidad) {
            $cita->productos()->create([
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio' => $producto->precio,
                'comision_pct' => $producto->comision_pct,
            ]);

            $cita->increment('precio_total', $producto->precio * $cantidad);
        });

        return $cita->fresh('productos');
    }

    private function crear(Local $local, Usuario $cliente, array $datos, string $canal, bool $confirmadaDirecto = false): Cita
    {
        $servicios = $this->cargarServicios($datos['servicios']);
        $duracionTotal = (int) $servicios->sum('duracion_min') + (int) $servicios->max('buffer_min');
        $inicio = CarbonImmutable::parse($datos['inicio']);
        $fin = $inicio->addMinutes($duracionTotal);
        $comisionPct = $this->comisionPct($local->id, $datos['profesional_id']);
        $clienteNuevo = ! ClienteLocal::where('usuario_id', $cliente->id)->where('local_id', $local->id)->exists();

        $clientePerfil = ClientePerfil::where('usuario_id', $cliente->id)->first();
        $naceConfirmada = $confirmadaDirecto || (bool) ($clientePerfil?->requiere_confirmacion ?? false);

        try {
            $cita = DB::transaction(function () use (
                $local, $cliente, $datos, $servicios, $inicio, $fin, $comisionPct, $clienteNuevo, $canal, $naceConfirmada,
            ) {
                $cita = Cita::create([
                    'local_id' => $local->id,
                    'profesional_id' => $datos['profesional_id'],
                    'recurso_id' => $datos['recurso_id'] ?? null,
                    'cliente_id' => $cliente->id,
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'estado' => $naceConfirmada ? 'confirmada' : 'reservada',
                    'canal' => $canal,
                    'precio_total' => $servicios->sum('precio'),
                    // DEFAULT 0 en Postgres, no en PHP — sin esto, `propina`
                    // queda `null` en memoria hasta un `fresh()` y
                    // `CompletarCita` sin propina explícita revienta el
                    // NOT NULL al guardar `$propina ?? $cita->propina`.
                    'propina' => 0,
                    'cliente_nuevo' => $clienteNuevo,
                    'para_tipo' => $datos['para_tipo'] ?? 'titular',
                    'para_nombre' => $datos['para_nombre'] ?? null,
                    'mascota_id' => $datos['mascota_id'] ?? null,
                    'nota_cliente' => $datos['nota_cliente'] ?? null,
                    'reagendada_de_id' => $datos['reagendada_de_id'] ?? null,
                    'expira_at' => $naceConfirmada ? null : now()->addMinutes(10),
                    'confirmada_at' => $naceConfirmada ? now() : null,
                    'codigo' => strtoupper(Str::random(8)),
                ]);

                foreach ($servicios as $servicio) {
                    $cita->items()->create([
                        'servicio_local_id' => $servicio->id,
                        'precio' => $servicio->precio,
                        'duracion_min' => $servicio->duracion_min,
                        'comisionable' => $servicio->comisionable,
                        'comision_pct' => $servicio->comisionable ? $comisionPct : 0,
                    ]);
                }

                return $cita;
            });
        } catch (QueryException $e) {
            if ($e->getCode() !== self::EXCLUSION_VIOLATION) {
                throw $e;
            }

            throw new SlotYaOcupado;
        }

        app(EsperaService::class)->convertirSiCorresponde($cita);
        CitaCreada::dispatch($cita->id);

        return $cita->load('items');
    }

    private function cargarServicios(array $ids): Collection
    {
        $servicios = ServicioLocal::whereIn('id', $ids)->where('activo', true)->get();

        if ($servicios->count() !== count($ids)) {
            throw_validacion('Alguno de los servicios pedidos no existe o no está activo.', 'servicios');
        }

        return $servicios;
    }

    private function comisionPct(string $localId, string $profesionalId): float
    {
        return (float) (Asignacion::where('local_id', $localId)
            ->where('profesional_id', $profesionalId)
            ->vigente()
            ->value('comision_pct') ?? 0);
    }

    private function resolverClienteWalkIn(array $datos): Usuario
    {
        if (isset($datos['cliente_id'])) {
            return Usuario::findOrFail($datos['cliente_id']);
        }

        // Cliente sombra mínimo (§4.3): sin contraseña, sin OTP, sin
        // consentimientos — eso es responsabilidad de Identity. Aquí solo
        // hace falta un `usuario_id` del que colgar la cita.
        return Usuario::firstOrCreate(
            ['telefono' => $datos['telefono']],
            ['nombre' => $datos['nombre'], 'password_hash' => null],
        );
    }
}
