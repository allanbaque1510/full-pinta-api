<?php

namespace Tests\Feature\Notifications;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Preferencias por categoría (§4.9, §11): default `true` hasta que el usuario las toque. */
class PreferenciaNotificacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_las_categorias_con_el_default_activado(): void
    {
        $this->seed();
        $usuario = Usuario::factory()->create();

        $respuesta = $this->withHeader('Authorization', "Bearer {$usuario->createToken('t')->plainTextToken}")
            ->getJson('/api/v1/mis-preferencias-notificacion')
            ->assertOk();

        $categorias = collect($respuesta->json())->pluck('categoria')->all();

        $this->assertContains('citas', $categorias);
        $this->assertContains('promos', $categorias);
        $this->assertTrue(collect($respuesta->json())->every(fn ($p) => $p['push'] === true && $p['whatsapp'] === true));
    }

    public function test_sincronizar_actualiza_solo_las_categorias_enviadas(): void
    {
        $this->seed();
        $usuario = Usuario::factory()->create();
        $token = "Bearer {$usuario->createToken('t')->plainTextToken}";

        $this->withHeader('Authorization', $token)
            ->putJson('/api/v1/mis-preferencias-notificacion', [
                'preferencias' => [
                    ['categoria' => 'promos', 'push' => false, 'whatsapp' => false],
                ],
            ])
            ->assertOk();

        $respuesta = $this->withHeader('Authorization', $token)
            ->getJson('/api/v1/mis-preferencias-notificacion')
            ->assertOk();

        $porCategoria = collect($respuesta->json())->keyBy('categoria');

        $this->assertFalse($porCategoria['promos']['push']);
        $this->assertTrue($porCategoria['citas']['push']); // no tocada, sigue en default
    }
}
