<?php

namespace App\Modules\Directory\Application;

use App\Models\Favorito;
use App\Models\Local;
use App\Models\Negocio;
use App\Models\Usuario;
use App\Modules\Directory\Http\Resources\LocalPublicoResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;

/**
 * Todo lo que se puede hacer con un `Local` (§4.4), agrupado en un solo
 * archivo — alta, edición y las dos transiciones de `estado` que el dueño
 * controla — para tener la lógica del modelo en un lugar y poder reusarla
 * desde cualquier controlador o job que la necesite.
 */
final readonly class LocalService
{
    private const TTL_CACHE_PERFIL_SEGUNDOS = 3600;

    public function listarPorNegocio(Negocio $negocio): Collection
    {
        return $negocio->locales;
    }

    /**
     * Perfil público (§7.4): lo que un cliente necesita para decidir, cacheado
     * 1 h e invalidado al editar (`actualizar()`/`activar()`/`pausar()`) — no
     * hace falta evento de dominio, la invalidación es intra-módulo.
     *
     * 404 en vez de 403 si el local no está `activo`: no hay que confirmar al
     * público que el local existe pero está oculto.
     *
     * Se cachea el array YA RESUELTO por `LocalPublicoResource`, no el modelo
     * con sus relaciones cargadas: `cache.serializable_classes = false`
     * (default de seguridad de Laravel, para blindarse de gadget-chain
     * attacks si se filtra la `APP_KEY`) hace que Redis convierta cualquier
     * objeto leído del caché en `__PHP_Incomplete_Class` — un `Local` con
     * relaciones Eloquent revienta ahí. Cachear el array final además evita
     * reconstruir el árbol de relaciones en cada lectura.
     *
     * `->resolve()` NO alcanza: solo resuelve el array del nivel superior —
     * `amenidades`/`fotos`/`horarios`/`servicios` quedan como objetos
     * `AnonymousResourceCollection` sin resolver (esos se resuelven recién
     * cuando `json_encode` los recorre al armar la respuesta HTTP, no antes),
     * y son objetos igual de rechazados por Redis. El viaje de ida y vuelta
     * por JSON fuerza la resolución recursiva completa, dejando solo arrays y
     * escalares.
     *
     * `$usuario` es el cliente autenticado, si lo hay (la ruta es pública, no
     * exige sesión) — resuelve `es_favorito`. Se agrega DESPUÉS de leer del
     * caché, nunca dentro del closure de `Cache::remember()`: ese bloque se
     * comparte entre TODOS los que consulten este local durante la hora de
     * TTL, así que si `es_favorito` entrara ahí, el primer usuario que
     * "calienta" el caché le fijaría su propio valor a cualquiera que lo lea
     * después.
     *
     * @return array<string, mixed>
     */
    public function perfilPublico(Local $local, ?Usuario $usuario = null): array
    {
        if ($local->estado !== 'activo') {
            throw new ModelNotFoundException;
        }

        $perfil = Cache::remember(
            $this->clavePerfilPublico($local->id),
            self::TTL_CACHE_PERFIL_SEGUNDOS,
            fn () => json_decode(json_encode(LocalPublicoResource::make($local->load([
                'servicios' => fn ($q) => $q->where('activo', true),
                'servicios.catalogoServicio',
                'amenidades',
                'fotos',
                'horarios',
                'resenas' => fn ($q) => $q->publicadas(),
            ]))), true),
        );

        $perfil['es_favorito'] = $usuario !== null
            && Favorito::where('usuario_id', $usuario->id)->where('local_id', $local->id)->exists();

        return $perfil;
    }

    /**
     * Da de alta un local bajo un negocio. Nace en 'borrador' (§4.4) a
     * propósito: el dueño todavía tiene que cargar servicios, horarios y
     * fotos antes de que sea buscable — `activar()` es un paso aparte,
     * deliberado.
     *
     * @param  array{
     *     nombre: string, direccion: string, referencia?: ?string,
     *     lat: float, lng: float, telefono?: ?string, whatsapp?: ?string,
     *     lead_time_min?: int, horizonte_dias?: int, politica_cancelacion_horas?: int,
     * }  $datos
     */
    public function crear(Negocio $negocio, array $datos): Local
    {
        return $negocio->locales()->create([
            // `lead_time_min`/`horizonte_dias`/`politica_cancelacion_horas`
            // son opcionales en el request pero tienen DEFAULT en Postgres
            // (60/30/2) — ese default vive en la base, no en PHP. Si se deja
            // que Eloquent omita la columna, el modelo recién creado queda
            // con esos campos en `null` en memoria hasta que alguien lo
            // recargue. Se repite el mismo valor aquí a propósito: es
            // documentación ejecutable de cuál es el default real.
            'lead_time_min' => 60,
            'horizonte_dias' => 30,
            'politica_cancelacion_horas' => 2,
            ...array_diff_key($datos, ['lat' => true, 'lng' => true]),
            'ubicacion' => ['lat' => $datos['lat'], 'lng' => $datos['lng']],
            'estado' => 'borrador',
            'verificado' => false,
        ]);
    }

    /**
     * `lat`/`lng` son especiales: si llega solo uno de los dos, se completa
     * con el valor que el local ya tenía — de otro modo un `PATCH` con solo
     * `lat` movería el punto al (lat, 0).
     */
    public function actualizar(Local $local, array $datos): Local
    {
        $tieneCoordenada = array_key_exists('lat', $datos) || array_key_exists('lng', $datos);
        $planos = array_diff_key($datos, ['lat' => true, 'lng' => true]);

        if ($tieneCoordenada) {
            $planos['ubicacion'] = [
                'lat' => $datos['lat'] ?? $local->ubicacion['lat'],
                'lng' => $datos['lng'] ?? $local->ubicacion['lng'],
            ];
        }

        $local->update($planos);

        Cache::forget($this->clavePerfilPublico($local->id));

        return $local;
    }

    /**
     * `local.estado` no es un booleano — es una máquina de estados chica:
     * `borrador → activo`, y `pausado → activo` cuando el dueño decide
     * reabrir.
     *
     * `suspendido` es deliberadamente un callejón sin salida desde aquí: lo
     * pone la plataforma (moderación, incumplimiento de términos) y no se
     * reactiva solo con esta acción — necesita intervención de soporte, que
     * todavía no tiene panel propio (ver `context/plan-implementacion.md`).
     */
    public function activar(Local $local): Local
    {
        if (! in_array($local->estado, ['borrador', 'pausado'], true)) {
            throw_validacion("No se puede activar un local en estado '{$local->estado}'.", 'estado');
        }

        $local->update(['estado' => 'activo']);

        Cache::forget($this->clavePerfilPublico($local->id));

        return $local;
    }

    /**
     * El dueño pausa su propio local (vacaciones, remodelación). A
     * diferencia de `suspendido` —que pone la plataforma—, `pausado` es
     * reversible por el mismo dueño en cualquier momento vía `activar()`.
     */
    public function pausar(Local $local): Local
    {
        if ($local->estado !== 'activo') {
            throw_validacion("No se puede pausar un local en estado '{$local->estado}'; solo uno 'activo'.", 'estado');
        }

        $local->update(['estado' => 'pausado']);

        Cache::forget($this->clavePerfilPublico($local->id));

        return $local;
    }

    private function clavePerfilPublico(string $localId): string
    {
        return "local:perfil-publico:{$localId}";
    }
}
