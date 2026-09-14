<?php

namespace Tests\Feature\Directory;

use App\Models\Amenidad;
use App\Models\CatalogoServicio;
use App\Models\Local;
use App\Models\ServicioCategoria;
use App\Models\ServicioLocal;
use App\Models\Vertical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Búsqueda por cercanía (§7.1-7.3, §8). Punto de referencia: el centro de
 * Guayaquil que ya usa `LocalFactory` para sus coordenadas por defecto.
 */
class BusquedaLocalTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = -2.1300;

    private const LNG = -79.8862;

    public function test_encuentra_locales_activos_dentro_del_radio_ordenados_por_distancia(): void
    {
        $cercano = Local::factory()->create(['ubicacion' => ['lat' => self::LAT, 'lng' => self::LNG]]);
        $medio = Local::factory()->create(['ubicacion' => ['lat' => -2.145, 'lng' => self::LNG]]);
        $lejano = Local::factory()->create(['ubicacion' => ['lat' => -2.23, 'lng' => self::LNG]]);

        $respuesta = $this->getJson('/api/v1/buscar/locales?lat='.self::LAT.'&lng='.self::LNG)
            ->assertOk();

        $ids = collect($respuesta->json())->pluck('id')->all();

        $this->assertSame([$cercano->id, $medio->id], $ids);
        $this->assertNotContains($lejano->id, $ids);
    }

    public function test_no_incluye_locales_que_no_estan_activos(): void
    {
        Local::factory()->borrador()->create(['ubicacion' => ['lat' => self::LAT, 'lng' => self::LNG]]);

        $respuesta = $this->getJson('/api/v1/buscar/locales?lat='.self::LAT.'&lng='.self::LNG)->assertOk();

        $this->assertCount(0, $respuesta->json());
    }

    public function test_filtra_por_vertical(): void
    {
        $vertical = Vertical::factory()->create();
        $categoria = ServicioCategoria::factory()->create(['vertical_id' => $vertical->id]);
        $catalogo = CatalogoServicio::factory()->create(['categoria_id' => $categoria->id]);

        $conVertical = Local::factory()->create(['ubicacion' => ['lat' => self::LAT, 'lng' => self::LNG]]);
        ServicioLocal::factory()->create(['local_id' => $conVertical->id, 'catalogo_servicio_id' => $catalogo->id]);

        $sinVertical = Local::factory()->create(['ubicacion' => ['lat' => self::LAT, 'lng' => self::LNG]]);

        $respuesta = $this->getJson('/api/v1/buscar/locales?lat='.self::LAT.'&lng='.self::LNG.'&vertical='.$vertical->codigo)
            ->assertOk();

        $ids = collect($respuesta->json())->pluck('id')->all();

        $this->assertSame([$conVertical->id], $ids);
        $this->assertNotContains($sinVertical->id, $ids);
    }

    public function test_filtra_por_rango_de_precio(): void
    {
        $barato = Local::factory()->create(['ubicacion' => ['lat' => self::LAT, 'lng' => self::LNG]]);
        ServicioLocal::factory()->create(['local_id' => $barato->id, 'precio' => 8, 'activo' => true]);

        $caro = Local::factory()->create(['ubicacion' => ['lat' => self::LAT, 'lng' => self::LNG]]);
        ServicioLocal::factory()->create(['local_id' => $caro->id, 'precio' => 80, 'activo' => true]);

        $respuesta = $this->getJson('/api/v1/buscar/locales?lat='.self::LAT.'&lng='.self::LNG.'&precio_min=5&precio_max=20')
            ->assertOk();

        $ids = collect($respuesta->json())->pluck('id')->all();

        $this->assertSame([$barato->id], $ids);
        $this->assertNotContains($caro->id, $ids);
    }

    public function test_filtro_de_amenidades_exige_todas_las_pedidas_no_cualquiera(): void
    {
        $wifi = Amenidad::factory()->create();
        $parqueo = Amenidad::factory()->create();

        $conAmbas = Local::factory()->create(['ubicacion' => ['lat' => self::LAT, 'lng' => self::LNG]]);
        $conAmbas->amenidades()->attach([$wifi->id, $parqueo->id]);

        $conUnaSola = Local::factory()->create(['ubicacion' => ['lat' => self::LAT, 'lng' => self::LNG]]);
        $conUnaSola->amenidades()->attach([$wifi->id]);

        $respuesta = $this->getJson(
            '/api/v1/buscar/locales?lat='.self::LAT.'&lng='.self::LNG
                .'&amenidades[]='.$wifi->codigo.'&amenidades[]='.$parqueo->codigo,
        )->assertOk();

        $ids = collect($respuesta->json())->pluck('id')->all();

        $this->assertSame([$conAmbas->id], $ids);
        $this->assertNotContains($conUnaSola->id, $ids);
    }
}
