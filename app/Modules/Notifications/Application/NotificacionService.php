<?php

namespace App\Modules\Notifications\Application;

use App\Models\Notificacion;
use App\Models\NotificacionCategoria;
use App\Models\PreferenciaNotificacion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Todo lo que este módulo sabe hacer con una `Notificacion` (§4.9, §11):
 * programarla respetando preferencias, cancelar las pendientes de una cita
 * (§6/§11.9 — "cada transición cancela y reprograma"), y marcar el resultado
 * del envío.
 */
final readonly class NotificacionService
{
    private const SQLSTATE_UNIQUE_VIOLATION = '23505';

    /**
     * @param  list<string>  $canales  ('push', 'whatsapp', 'websocket', 'sms')
     */
    public function programar(
        Usuario $usuario,
        string $tipoEvento,
        string $categoriaCodigo,
        array $canales,
        ?string $citaId = null,
        ?string $plantilla = null,
        bool $obligatorio = false,
        ?CarbonImmutable $programadaPara = null,
    ): void {
        // Conjunto cerrado y fijo (§4.9: citas, agenda, social, promos), con
        // su propio seeder — pero listeners de eventos de dominio corren en
        // cualquier test que dispare esos eventos, no solo en los de este
        // módulo. Mismo patrón de autocuración que ya usan las factories de
        // otras tablas de parámetros (`CatalogoServicioFactory` con
        // `TipoRecurso`/`ServicioCategoria`) para no depender de que el
        // seeder haya corrido.
        $categoria = NotificacionCategoria::where('codigo', $categoriaCodigo)->first()
            ?? NotificacionCategoria::create(['codigo' => $categoriaCodigo, 'nombre' => ucfirst($categoriaCodigo), 'activo' => true]);

        foreach ($canales as $canal) {
            if (! $obligatorio && ! $this->prefiereCanal($usuario, $categoria, $canal)) {
                continue;
            }

            $this->crear($usuario, $tipoEvento, $categoria->id, $canal, $citaId, $plantilla, $programadaPara);
        }
    }

    private function crear(
        Usuario $usuario,
        string $tipoEvento,
        string $categoriaId,
        string $canal,
        ?string $citaId,
        ?string $plantilla,
        ?CarbonImmutable $programadaPara,
    ): void {
        try {
            // `DB::transaction()` en vez de un `create()` suelto: si ya
            // estamos dentro de otra transacción (el `DB::transaction()` de
            // `CitaService::crear()`, o la transacción de test de
            // `RefreshDatabase`), Postgres exige un `SAVEPOINT` para poder
            // recuperarse de una violación de constraint sin abortar TODA la
            // transacción externa — un `catch` a secas alrededor de un
            // `create()` suelto deja la conexión en "transacción abortada"
            // (SQLSTATE 25P02) para cualquier consulta posterior.
            DB::transaction(function () use ($usuario, $tipoEvento, $categoriaId, $canal, $citaId, $plantilla, $programadaPara) {
                Notificacion::create([
                    'usuario_id' => $usuario->id,
                    'tipo_evento' => $tipoEvento,
                    'categoria_id' => $categoriaId,
                    'canal' => $canal,
                    'cita_id' => $citaId,
                    'plantilla' => $canal === 'whatsapp' ? ($plantilla ?? $tipoEvento) : null,
                    'estado' => 'programada',
                    'programada_para' => $programadaPara ?? now(),
                ]);
            });
        } catch (QueryException $e) {
            if ($e->getCode() !== self::SQLSTATE_UNIQUE_VIOLATION) {
                throw $e;
            }

            // `UNIQUE(cita_id, tipo_evento, canal)` ya cubría este envío —
            // reintento de la cola o doble llamada del mismo evento. Se
            // ignora en silencio, es el seguro contra duplicados (§11.9).
        }
    }

    /** Preferencia del usuario para ese canal en esa categoría; `true` si nunca la tocó (default del esquema). */
    private function prefiereCanal(Usuario $usuario, NotificacionCategoria $categoria, string $canal): bool
    {
        if (! in_array($canal, ['push', 'whatsapp'], true)) {
            // `websocket`/`sms` no tienen preferencia modelada en §4.9.
            return true;
        }

        $preferencia = PreferenciaNotificacion::where('usuario_id', $usuario->id)
            ->where('categoria_id', $categoria->id)
            ->first();

        return $preferencia?->{$canal} ?? true;
    }

    /** El "cancela y reprograma" de cada transición de estado (§6, §11.9). */
    public function cancelarPendientesDeCita(string $citaId): void
    {
        Notificacion::where('cita_id', $citaId)->where('estado', 'programada')->update(['estado' => 'cancelada']);
    }

    public function marcarEnviada(Notificacion $notificacion, ?string $proveedorId, float $costoUsd = 0): void
    {
        $notificacion->update([
            'estado' => 'enviada', 'enviada_at' => now(), 'proveedor_id' => $proveedorId, 'costo_usd' => $costoUsd,
        ]);
    }

    public function marcarFallida(Notificacion $notificacion, string $error): void
    {
        $notificacion->update(['estado' => 'fallida', 'error' => $error]);
    }

    /**
     * Ventana de silencio 8:00-21:00 hora de Guayaquil (§11.9, punto 3): si el
     * horario natural cae fuera de esa ventana, se mueve a las 20:00 de la
     * noche anterior — regla única y explícita, en vez de bifurcar caso por
     * caso "cae de madrugada" vs "cae muy tarde".
     *
     * Ecuador continental no tiene horario de verano, así que esto equivale a
     * UTC-5 fijo — se usa el identificador de zona real en vez de restar 5
     * horas a mano, por ser más explícito.
     */
    public function ajustarAVentanaDeEnvio(CarbonImmutable $momento): CarbonImmutable
    {
        $local = $momento->setTimezone('America/Guayaquil');

        if ($local->hour >= 8 && $local->hour < 21) {
            return $momento;
        }

        $base = $local->hour >= 21 ? $local : $local->subDay();

        return $base->setTime(20, 0)->setTimezone('UTC');
    }
}
