<?php

namespace App\Modules\Directory\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda por cercanía (§7, §8): el punto de entrada del cliente antes de
 * elegir un local. Query builder crudo, no Eloquent — con los `JOIN`
 * condicionales que exige cada filtro, un `Model::with()` no da ese control.
 *
 * `ST_DWithin`/`ST_Distance` sobre `local.ubicacion` (`geography`, §4.4) hacen
 * el filtro y el orden de cercanía; el resto de columnas del `SELECT` se
 * agrupa por `l.id` — Postgres lo permite sin listarlas todas porque son
 * funcionalmente dependientes de la PK (§4.4).
 */
final readonly class BusquedaLocalService
{
    private const RADIO_M_DEFAULT = 5000;

    private const LIMIT_DEFAULT = 20;

    private const TTL_CACHE_SEGUNDOS = 60;

    /**
     * @param  array{
     *     lat: float, lng: float, radio_m?: int, vertical?: string,
     *     catalogo_servicio_id?: string, precio_min?: float, precio_max?: float,
     *     amenidades?: array<int,string>, disponible?: bool, fecha?: string,
     *     abierto_ahora?: bool, page?: int, limit?: int,
     * }  $filtros
     */
    public function buscar(array $filtros): Collection
    {
        // Sustituto pragmático de un geohash real (no hay librería instalada):
        // redondear a 2 decimales agrupa puntos dentro de ~1 km en la misma
        // clave de caché, que es la resolución que le sirve a este filtro.
        $claveLat = round($filtros['lat'], 2);
        $claveLng = round($filtros['lng'], 2);
        $clave = 'busqueda:locales:'.md5(json_encode([$claveLat, $claveLng, $filtros]));

        // `cache.serializable_classes` está en `false` (default de Laravel: sin
        // eso, un `APP_KEY` filtrado abriría la puerta a gadget-chain attacks
        // vía objetos deserializados desde el caché) — Redis con `allowed_classes:
        // false` convierte CUALQUIER objeto cacheado en `__PHP_Incomplete_Class`
        // al leerlo. Se cachea el array plano (siempre seguro) y se reconstruyen
        // los `stdClass` al leer, para no tocar `BusquedaLocalResource`.
        $filas = Cache::remember(
            $clave,
            self::TTL_CACHE_SEGUNDOS,
            fn () => $this->ejecutar($filtros)->map(fn ($fila) => (array) $fila)->all(),
        );

        return collect($filas)->map(fn (array $fila) => (object) $fila);
    }

    /**
     * @param  array{
     *     lat: float, lng: float, radio_m?: int, vertical?: string,
     *     catalogo_servicio_id?: string, precio_min?: float, precio_max?: float,
     *     amenidades?: array<int,string>, disponible?: bool, fecha?: string,
     *     abierto_ahora?: bool, page?: int, limit?: int,
     * }  $filtros
     */
    private function ejecutar(array $filtros): Collection
    {
        $punto = sprintf("ST_GeogFromText('SRID=4326;POINT(%F %F)')", $filtros['lng'], $filtros['lat']);
        $radioM = $filtros['radio_m'] ?? self::RADIO_M_DEFAULT;

        $query = DB::table('local as l')
            ->select([
                'l.id', 'l.negocio_id', 'l.nombre', 'l.direccion', 'l.telefono', 'l.whatsapp',
                'l.verificado', 'l.score_ranking',
            ])
            ->selectRaw('ST_Y(l.ubicacion::geometry) as lat, ST_X(l.ubicacion::geometry) as lng')
            ->selectRaw("ST_Distance(l.ubicacion, {$punto}) as distancia_m")
            ->where('l.estado', 'activo')
            ->whereRaw("ST_DWithin(l.ubicacion, {$punto}, ?)", [$radioM])
            ->groupBy('l.id');

        $requiereServicio = isset($filtros['vertical']) || isset($filtros['catalogo_servicio_id'])
            || isset($filtros['precio_min']) || isset($filtros['precio_max']);

        if ($requiereServicio) {
            $query->join('servicio_local as sl', fn ($join) => $join
                ->on('sl.local_id', '=', 'l.id')
                ->where('sl.activo', true));
            $query->join('catalogo_servicio as cs', 'cs.id', '=', 'sl.catalogo_servicio_id');

            if (isset($filtros['vertical'])) {
                $verticalId = DB::table('vertical')->where('codigo', $filtros['vertical'])->value('id');

                $query->join('servicio_categoria as sc', 'sc.id', '=', 'cs.categoria_id')
                    ->where('sc.vertical_id', $verticalId);
            }

            if (isset($filtros['catalogo_servicio_id'])) {
                $query->where('cs.id', $filtros['catalogo_servicio_id']);
            }

            if (isset($filtros['precio_min'])) {
                $query->where('sl.precio', '>=', $filtros['precio_min']);
            }

            if (isset($filtros['precio_max'])) {
                $query->where('sl.precio', '<=', $filtros['precio_max']);
            }
        }

        if (! empty($filtros['amenidades'])) {
            $amenidadIds = DB::table('amenidad')->whereIn('codigo', $filtros['amenidades'])->pluck('id');

            $query->join('local_amenidad as la', fn ($join) => $join
                ->on('la.local_id', '=', 'l.id')
                ->whereIn('la.amenidad_id', $amenidadIds));
            $query->havingRaw('COUNT(DISTINCT la.amenidad_id) = ?', [$amenidadIds->count()]);
        }

        if (! empty($filtros['disponible'])) {
            $fecha = isset($filtros['fecha']) ? CarbonImmutable::parse($filtros['fecha']) : CarbonImmutable::now();

            $query->join('disponibilidad_dia as dd', fn ($join) => $join
                ->on('dd.local_id', '=', 'l.id')
                ->where('dd.fecha', $fecha->toDateString()))
                ->where('dd.slots_libres', '>', 0);
        }

        if (! empty($filtros['abierto_ahora'])) {
            $ahora = CarbonImmutable::now();

            $query->join('horario_local as hl', fn ($join) => $join
                ->on('hl.local_id', '=', 'l.id')
                ->where('hl.dia_semana', $ahora->dayOfWeek))
                ->where('hl.abre', '<=', $ahora->format('H:i:s'))
                ->where('hl.cierra', '>', $ahora->format('H:i:s'));
        }

        $limit = $filtros['limit'] ?? self::LIMIT_DEFAULT;
        $page = $filtros['page'] ?? 1;

        return $query
            ->orderBy('l.score_ranking', 'desc')
            ->orderBy('distancia_m')
            ->forPage($page, $limit)
            ->get();
    }
}
