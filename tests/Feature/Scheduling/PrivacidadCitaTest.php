<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Privacidad §3.3: el teléfono del cliente solo se revela desde que la cita
 * está confirmada — antes de eso el local no lo necesita.
 */
class PrivacidadCitaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_telefono_del_cliente_esta_ausente_en_una_cita_reservada(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->reservada()->create(['cliente_id' => $cliente->id]);

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/citas/{$cita->id}")
            ->assertOk()
            ->assertJsonPath('cliente_telefono', null);
    }

    public function test_el_telefono_del_cliente_esta_presente_en_una_cita_confirmada(): void
    {
        $cliente = Usuario::factory()->create();
        $cita = Cita::factory()->create(['cliente_id' => $cliente->id]); // nace confirmada

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/citas/{$cita->id}")
            ->assertOk()
            ->assertJsonPath('cliente_telefono', $cliente->telefono);
    }
}
