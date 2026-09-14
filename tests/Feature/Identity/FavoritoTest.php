<?php

namespace Tests\Feature\Identity;

use App\Models\Favorito;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Favoritos (§7): un solo endpoint hace de alta y baja; un favorito es de un
 * local o de un profesional, nunca de ambos (§4.3).
 */
class FavoritoTest extends TestCase
{
    use RefreshDatabase;

    public function test_alternar_agrega_un_local_a_favoritos_y_alternar_de_nuevo_lo_quita(): void
    {
        $cliente = Usuario::factory()->create();
        $local = Local::factory()->create();
        $token = $cliente->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/favoritos', ['local_id' => $local->id])
            ->assertOk()
            ->assertJsonPath('agregado', true);

        $this->assertDatabaseHas('favorito', ['usuario_id' => $cliente->id, 'local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/favoritos', ['local_id' => $local->id])
            ->assertOk()
            ->assertJsonPath('agregado', false);

        $this->assertDatabaseMissing('favorito', ['usuario_id' => $cliente->id, 'local_id' => $local->id]);
    }

    public function test_no_se_puede_marcar_favorito_local_y_profesional_a_la_vez(): void
    {
        $cliente = Usuario::factory()->create();
        $local = Local::factory()->create();
        $profesional = Profesional::factory()->create();

        $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->postJson('/api/v1/favoritos', ['local_id' => $local->id, 'profesional_id' => $profesional->id])
            ->assertUnprocessable();
    }

    public function test_lista_solo_los_favoritos_del_usuario_autenticado(): void
    {
        $cliente = Usuario::factory()->create();
        Favorito::factory()->create(['usuario_id' => $cliente->id]);
        Favorito::factory()->deProfesional()->create(['usuario_id' => $cliente->id]);
        Favorito::factory()->create(); // de otro usuario

        $respuesta = $this->withHeader('Authorization', "Bearer {$cliente->createToken('t')->plainTextToken}")
            ->getJson('/api/v1/mis-favoritos')
            ->assertOk();

        $this->assertCount(2, $respuesta->json());
    }
}
