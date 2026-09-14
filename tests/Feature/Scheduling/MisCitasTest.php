<?php

namespace Tests\Feature\Scheduling;

use App\Models\Cita;
use App\Models\Local;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historial del cliente (§7.6): cruza todos los locales que visitó, sin
 * scoping a uno.
 */
class MisCitasTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_solo_las_citas_del_cliente_autenticado_en_todos_sus_locales(): void
    {
        $cliente = Usuario::factory()->create();
        $localA = Local::factory()->create();
        $localB = Local::factory()->create();

        $miaEnA = Cita::factory()->create(['cliente_id' => $cliente->id, 'local_id' => $localA->id]);
        $miaEnB = Cita::factory()->create(['cliente_id' => $cliente->id, 'local_id' => $localB->id]);
        Cita::factory()->create(); // de otro cliente

        $respuesta = $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->getJson('/api/v1/mis-citas')
            ->assertOk();

        $ids = collect($respuesta->json())->pluck('id')->all();

        $this->assertCount(2, $ids);
        $this->assertContains($miaEnA->id, $ids);
        $this->assertContains($miaEnB->id, $ids);
    }

    public function test_filtra_por_estado(): void
    {
        $cliente = Usuario::factory()->create();
        $completada = Cita::factory()->completada()->create(['cliente_id' => $cliente->id]);
        Cita::factory()->create(['cliente_id' => $cliente->id]); // confirmada

        $respuesta = $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->getJson('/api/v1/mis-citas?estado=completada')
            ->assertOk();

        $ids = collect($respuesta->json())->pluck('id')->all();

        $this->assertSame([$completada->id], $ids);
    }
}
