<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogoServicio;
use App\Models\Recurso;
use App\Models\Rubro;
use App\Models\ServicioCategoria;
use App\Models\ServicioLocal;
use App\Models\TamanoMascota;
use App\Models\TipoRecurso;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * El precio y la duración que un local concreto cobra por un servicio del
 * catálogo maestro (§4.5).
 */
class ServicioLocalTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // `SincronizarTamanosRequest` valida `tamano` contra `tamano_mascota` real.
        foreach (['pequeno', 'grande', 'gigante'] as $codigo) {
            TamanoMascota::factory()->create(['codigo' => $codigo, 'nombre' => $codigo]);
        }
    }

    public function test_el_propietario_puede_dar_de_alta_un_servicio(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $catalogo = CatalogoServicio::factory()->create(['nombre' => 'Corte fade']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 8.5, 'duracion_min' => 30,
            ])
            ->assertCreated()
            ->assertJsonPath('precio', '8.50')
            ->assertJsonPath('nombre', 'Corte fade')
            ->assertJsonPath('activo', true)
            // Con DEFAULT en Postgres, no en PHP: si el servicio no los fija
            // explícito, el modelo recién creado devuelve `null` en vez del
            // valor real de la base.
            ->assertJsonPath('precio_desde', false)
            ->assertJsonPath('buffer_min', 0)
            ->assertJsonPath('comisionable', true);
    }

    public function test_no_se_puede_dar_de_alta_el_mismo_servicio_dos_veces(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $catalogo = CatalogoServicio::factory()->create();
        ServicioLocal::factory()->create(['local_id' => $local->id, 'catalogo_servicio_id' => $catalogo->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 5, 'duracion_min' => 20,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catalogo_servicio_id');
    }

    public function test_rechaza_un_servicio_de_catalogo_inactivo(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $catalogo = CatalogoServicio::factory()->create(['activo' => false]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 5, 'duracion_min' => 20,
            ])
            ->assertUnprocessable();
    }

    public function test_desactivar_un_servicio_no_lo_borra(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/servicios/{$servicio->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('servicio_local', ['id' => $servicio->id, 'activo' => false]);
    }

    public function test_sincronizar_tamanos_reemplaza_el_conjunto_completo(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $servicio = ServicioLocal::factory()->create([
            'local_id' => $local->id, 'catalogo_servicio_id' => $this->servicioDeMascotas()->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/servicios/{$servicio->id}/tamanos", [
                'tamanos' => [
                    ['tamano' => 'pequeno', 'precio' => 15, 'duracion_min' => 30],
                    ['tamano' => 'grande', 'precio' => 25, 'duracion_min' => 50],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/servicios/{$servicio->id}/tamanos", [
                'tamanos' => [['tamano' => 'gigante', 'precio' => 40, 'duracion_min' => 70]],
            ])
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.tamano', 'gigante');

        $this->assertSame(1, $servicio->tamanos()->count());
    }

    public function test_un_recepcionista_no_puede_crear_servicios(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);
        $catalogo = CatalogoServicio::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 5, 'duracion_min' => 20,
            ])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_listar_los_servicios(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/servicios")
            ->assertForbidden();
    }

    /**
     * Revisión de base de datos 2026-09-28: sin esta validación, un corte de
     * pelo de barbería podía terminar con precio por tamaño de mascota.
     */
    public function test_no_se_puede_sincronizar_tamanos_en_un_servicio_que_no_es_de_mascotas(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $servicio = ServicioLocal::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/servicios/{$servicio->id}/tamanos", [
                'tamanos' => [['tamano' => 'pequeno', 'precio' => 15, 'duracion_min' => 30]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tamanos');
    }

    /**
     * Revisión de base de datos 2026-09-28: un local que activa su primer
     * servicio de un tipo de recurso (mesa de uñas, tina...) no debe quedarse
     * sin poder agendar por no haber dado de alta manualmente esa unidad.
     */
    public function test_dar_de_alta_el_primer_servicio_de_un_tipo_de_recurso_crea_uno_por_defecto(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $mesaUnas = TipoRecurso::where('codigo', 'mesa_unas')->first()
            ?? TipoRecurso::factory()->create(['codigo' => 'mesa_unas', 'nombre' => 'Mesa de uñas']);
        $catalogo = CatalogoServicio::factory()->create(['tipo_recurso_id' => $mesaUnas->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 20, 'duracion_min' => 40,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('recurso', [
            'local_id' => $local->id, 'tipo_recurso_id' => $mesaUnas->id, 'nombre' => 'Mesa de uñas 1', 'activo' => true,
        ]);
    }

    public function test_dar_de_alta_un_segundo_servicio_del_mismo_tipo_de_recurso_no_duplica_el_recurso_por_defecto(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $mesaUnas = TipoRecurso::where('codigo', 'mesa_unas')->first()
            ?? TipoRecurso::factory()->create(['codigo' => 'mesa_unas', 'nombre' => 'Mesa de uñas']);
        $catalogoA = CatalogoServicio::factory()->create(['tipo_recurso_id' => $mesaUnas->id]);
        $catalogoB = CatalogoServicio::factory()->create(['tipo_recurso_id' => $mesaUnas->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogoA->id, 'precio' => 20, 'duracion_min' => 40,
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogoB->id, 'precio' => 25, 'duracion_min' => 50,
            ])->assertCreated();

        $this->assertSame(1, Recurso::where('local_id', $local->id)->where('tipo_recurso_id', $mesaUnas->id)->count());
    }

    public function test_dar_de_alta_un_servicio_sin_recurso_no_crea_ningun_recurso(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $ninguno = TipoRecurso::where('codigo', 'ninguno')->first()
            ?? TipoRecurso::factory()->create(['codigo' => 'ninguno', 'nombre' => 'Ninguno']);
        $catalogo = CatalogoServicio::factory()->create(['tipo_recurso_id' => $ninguno->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/servicios", [
                'catalogo_servicio_id' => $catalogo->id, 'precio' => 8, 'duracion_min' => 30,
            ])
            ->assertCreated();

        $this->assertSame(0, Recurso::where('local_id', $local->id)->count());
    }

    private function servicioDeMascotas(): CatalogoServicio
    {
        $rubro = Rubro::factory()->create(['codigo' => 'mascotas']);
        $categoria = ServicioCategoria::factory()->create(['rubro_id' => $rubro->id]);

        return CatalogoServicio::factory()->create(['categoria_id' => $categoria->id]);
    }
}
