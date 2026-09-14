<?php

namespace Tests\Feature\Staffing;

use App\Models\Recurso;
use App\Models\TipoRecurso;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * `Recurso` (§4.6): una fila por unidad física — "Silla 3", "Mesa 1" — nunca
 * una fila con cantidad.
 */
class RecursoTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // `CrearRecursoRequest` valida `tipo` contra `tipo_recurso` real.
        TipoRecurso::factory()->create(['codigo' => 'silla', 'nombre' => 'Silla']);
    }

    public function test_el_propietario_puede_crear_un_recurso(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/recursos", ['tipo' => 'silla', 'nombre' => 'Silla 1'])
            ->assertCreated()
            ->assertJsonPath('nombre', 'Silla 1')
            ->assertJsonPath('activo', true);
    }

    public function test_actualizar_un_recurso(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $recurso = Recurso::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/recursos/{$recurso->id}", ['nombre' => 'Silla 9'])
            ->assertOk()
            ->assertJsonPath('nombre', 'Silla 9');
    }

    public function test_desactivar_un_recurso_no_lo_borra(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $recurso = Recurso::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/recursos/{$recurso->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('recurso', ['id' => $recurso->id, 'activo' => false]);
    }

    public function test_un_recepcionista_no_puede_crear_recursos(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/recursos", ['tipo' => 'silla', 'nombre' => 'Silla 1'])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_listar_los_recursos(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/recursos")
            ->assertForbidden();
    }
}
