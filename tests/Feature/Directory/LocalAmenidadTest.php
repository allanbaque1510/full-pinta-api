<?php

namespace Tests\Feature\Directory;

use App\Models\Amenidad;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Sincronizar reemplaza el conjunto completo, no agrega ni quita una por una
 * (§4.4) — el front manda la lista final que quiere que quede.
 */
class LocalAmenidadTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_sincronizar_agrega_las_amenidades_elegidas_con_su_detalle(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $wifi = Amenidad::factory()->create(['codigo' => 'wifi']);
        $cerveza = Amenidad::factory()->create(['codigo' => 'cerveza']);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/amenidades", [
                'amenidades' => [
                    ['amenidad_id' => $wifi->id],
                    ['amenidad_id' => $cerveza->id, 'detalle' => 'Cerveza artesanal'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2);

        $this->assertDatabaseHas('local_amenidad', [
            'local_id' => $local->id, 'amenidad_id' => $cerveza->id, 'detalle' => 'Cerveza artesanal',
        ]);

        // El detalle guardado vuelve en la respuesta, no solo en la base —
        // el front lo necesita para mostrarlo, no solo para haberlo mandado.
        $conDetalle = collect($respuesta->json())->firstWhere('id', $cerveza->id);
        $this->assertSame('Cerveza artesanal', $conDetalle['detalle']);

        $sinDetalle = collect($respuesta->json())->firstWhere('id', $wifi->id);
        $this->assertNull($sinDetalle['detalle']);
    }

    public function test_sincronizar_de_nuevo_reemplaza_el_conjunto_no_lo_acumula(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $wifi = Amenidad::factory()->create();
        $cerveza = Amenidad::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/amenidades", [
                'amenidades' => [['amenidad_id' => $wifi->id], ['amenidad_id' => $cerveza->id]],
            ]);

        // Segunda llamada: solo wifi. Cerveza debe desaparecer, no acumularse.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/amenidades", [
                'amenidades' => [['amenidad_id' => $wifi->id]],
            ])
            ->assertOk()
            ->assertJsonCount(1);

        $this->assertSame(1, $local->fresh()->amenidades()->count());
    }

    public function test_sincronizar_con_una_lista_vacia_quita_todas(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $local->amenidades()->attach(Amenidad::factory()->create());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/amenidades", ['amenidades' => []])
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_rechaza_una_amenidad_inactiva(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $inactiva = Amenidad::factory()->create(['activo' => false]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/amenidades", [
                'amenidades' => [['amenidad_id' => $inactiva->id]],
            ])
            ->assertUnprocessable();
    }

    public function test_un_recepcionista_no_puede_sincronizar_amenidades(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/locales/{$local->id}/amenidades", ['amenidades' => []])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_ver_las_amenidades_del_local(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/amenidades")
            ->assertForbidden();
    }

    public function test_listar_las_amenidades_del_local_trae_el_detalle_guardado(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cerveza = Amenidad::factory()->create();
        $local->amenidades()->attach($cerveza->id, ['detalle' => 'Cerveza artesanal']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/locales/{$local->id}/amenidades")
            ->assertOk()
            ->assertJsonPath('0.detalle', 'Cerveza artesanal');
    }

    public function test_el_catalogo_publico_de_amenidades_no_lleva_detalle_de_ningun_local(): void
    {
        Amenidad::factory()->create();

        $this->getJson('/api/v1/amenidades')
            ->assertOk()
            ->assertJsonPath('0.detalle', null);
    }
}
