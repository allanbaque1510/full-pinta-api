<?php

namespace Tests\Feature\Directory;

use App\Models\Local;
use App\Models\NegocioMiembro;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Acceso real a la app para un negocio — admin o recepción (§3.2, §4.4).
 * Antes de este ítem, la única fila de `negocio_miembro` que se podía crear
 * era la del propietario, automática al crear el negocio.
 */
class NegocioMiembroTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_propietario_agrega_un_recepcionista_por_telefono(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $recepcionista = Usuario::factory()->create(['telefono' => '0991112222']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/miembros", [
                'telefono' => '0991112222', 'rol' => 'recepcion',
            ])
            ->assertCreated()
            ->assertJsonPath('rol', 'recepcion')
            ->assertJsonPath('usuario_id', $recepcionista->id)
            ->assertJsonPath('local_id', null);

        $this->assertDatabaseHas('negocio_miembro', [
            'usuario_id' => $recepcionista->id, 'negocio_id' => $negocio->id, 'rol_personal' => 'recepcion',
        ]);
    }

    public function test_el_nuevo_miembro_ya_puede_actuar_con_su_rol(): void
    {
        [, $token, $negocio, $local] = $this->propietarioConLocal();
        $recepcionista = Usuario::factory()->create(['telefono' => '0993334444']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/miembros", [
                'telefono' => '0993334444', 'rol' => 'recepcion',
            ])
            ->assertCreated();

        $tokenRecepcion = $recepcionista->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$tokenRecepcion}")
            ->getJson("/api/v1/locales/{$local->id}/citas")
            ->assertOk();
    }

    public function test_no_se_puede_agregar_con_rol_propietario(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        Usuario::factory()->create(['telefono' => '0995556666']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/miembros", [
                'telefono' => '0995556666', 'rol' => 'propietario',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rol');
    }

    public function test_rechaza_un_telefono_que_no_esta_registrado(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/miembros", [
                'telefono' => '0999998888', 'rol' => 'recepcion',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telefono');
    }

    public function test_un_local_id_de_otro_negocio_es_rechazado(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $localAjeno = Local::factory()->create();
        Usuario::factory()->create(['telefono' => '0997778888']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/negocios/{$negocio->id}/miembros", [
                'telefono' => '0997778888', 'rol' => 'recepcion', 'local_id' => $localAjeno->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('local_id');
    }

    public function test_un_recepcionista_no_puede_agregar_miembros(): void
    {
        [, , $negocio] = $this->propietarioConNegocio();
        [, $tokenRecepcion] = $this->recepcionEnNegocio($negocio);
        Usuario::factory()->create(['telefono' => '0991119999']);

        $this->withHeader('Authorization', "Bearer {$tokenRecepcion}")
            ->postJson("/api/v1/negocios/{$negocio->id}/miembros", [
                'telefono' => '0991119999', 'rol' => 'recepcion',
            ])
            ->assertForbidden();
    }

    public function test_listar_miembros_del_negocio(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        NegocioMiembro::factory()->recepcion()->create(['negocio_id' => $negocio->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/negocios/{$negocio->id}/miembros")
            ->assertOk()
            ->assertJsonCount(2); // propietario (automático al crear) + el recepcionista
    }

    public function test_terminar_revoca_sin_borrar_la_fila(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $miembro = NegocioMiembro::factory()->recepcion()->create(['negocio_id' => $negocio->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/miembros/{$miembro->id}/terminar")
            ->assertOk()
            ->assertJsonPath('hasta', now()->toDateString());

        $this->assertDatabaseHas('negocio_miembro', ['id' => $miembro->id]);
    }

    public function test_no_se_puede_terminar_la_membresia_del_propietario_legal(): void
    {
        [, $token, $negocio] = $this->propietarioConNegocio();
        $miembroPropietario = NegocioMiembro::where('negocio_id', $negocio->id)->where('rol_personal', 'propietario')->firstOrFail();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/miembros/{$miembroPropietario->id}/terminar")
            ->assertUnprocessable();
    }

    public function test_un_extrano_no_puede_listar_ni_agregar_miembros(): void
    {
        [, , $negocio] = $this->propietarioConNegocio();
        $extrano = Usuario::factory()->create();
        Usuario::factory()->create(['telefono' => '0994443333']);

        $this->withHeader('Authorization', "Bearer {$extrano->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/negocios/{$negocio->id}/miembros")
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$extrano->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/negocios/{$negocio->id}/miembros", ['telefono' => '0994443333', 'rol' => 'recepcion'])
            ->assertForbidden();
    }
}
