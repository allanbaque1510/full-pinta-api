<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

class ConfirmarCitaTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_cliente_puede_confirmar_su_propio_hold(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->reservada()->create(['cliente_id' => $cliente->id]);

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/confirmar")
            ->assertOk()
            ->assertJsonPath('estado', 'confirmada');

        $this->assertDatabaseHas('cita_evento', [
            'cita_id' => $cita->id, 'estado_anterior' => 'reservada', 'estado_nuevo' => 'confirmada', 'actor_rol' => 'cliente',
        ]);
    }

    public function test_el_staff_del_local_tambien_puede_confirmar(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $cita = Cita::factory()->reservada()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/citas/{$cita->id}/confirmar")
            ->assertOk();
    }

    public function test_no_se_puede_confirmar_una_cita_que_no_esta_reservada(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->create(['cliente_id' => $cliente->id]); // nace confirmada

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/confirmar")
            ->assertUnprocessable();
    }

    public function test_un_extrano_no_puede_confirmar_la_cita_de_otro(): void
    {
        $cita = Cita::factory()->reservada()->create();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/citas/{$cita->id}/confirmar")
            ->assertForbidden();
    }
}
