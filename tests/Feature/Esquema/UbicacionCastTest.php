<?php

namespace Tests\Feature\Esquema;

use App\Models\Local;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `local.ubicacion` es `geography(Point,4326)` (§4.4). Eloquent no la entiende
 * de forma nativa: este test prueba que el cast `App\Casts\Ubicacion` viaja
 * ida y vuelta contra Postgres real sin perder precisión, y que el índice
 * espacial sigue sirviendo para distancias — que es la razón de ser de la
 * columna (§4.1: "sin esto, 'barberías cerca de mí' hace scan completo").
 */
class UbicacionCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ubicacion_viaja_ida_y_vuelta_sin_perder_precision(): void
    {
        $local = Local::factory()->create(['ubicacion' => ['lat' => -2.1894, 'lng' => -79.8891]]);

        $leido = $local->fresh()->ubicacion;

        $this->assertEqualsWithDelta(-2.1894, $leido['lat'], 0.0001);
        $this->assertEqualsWithDelta(-79.8891, $leido['lng'], 0.0001);
    }

    public function test_postgis_puede_calcular_distancia_sobre_la_columna_real(): void
    {
        $centro = Local::factory()->create(['ubicacion' => ['lat' => -2.1894, 'lng' => -79.8891]]);
        $alborada = Local::factory()->create(['ubicacion' => ['lat' => -2.1300, 'lng' => -79.8862]]);

        $metros = DB::selectOne(
            'SELECT ST_Distance(
                (SELECT ubicacion FROM local WHERE id = ?),
                (SELECT ubicacion FROM local WHERE id = ?)
            ) AS metros',
            [$centro->id, $alborada->id],
        )->metros;

        // Guayaquil centro -> Alborada: unos 6.5 km en línea recta.
        $this->assertEqualsWithDelta(6576, $metros, 50);
    }

    public function test_el_indice_gist_espacial_existe(): void
    {
        $indice = DB::selectOne(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'local' AND indexname = 'local_ubicacion_gist'"
        );

        $this->assertNotNull($indice);
    }
}
