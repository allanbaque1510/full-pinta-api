<?php

namespace Tests\Feature\Identity;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autogestión del derecho de eliminación (§13.1) vía HTTP.
 */
class CuentaTest extends TestCase
{
    use RefreshDatabase;

    public function test_eliminar_la_propia_cuenta_la_anonimiza(): void
    {
        $usuario = Usuario::factory()->create(['telefono' => '0970009999', 'nombre' => 'Alguien']);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/cuenta')
            ->assertNoContent();

        $usuario->refresh();
        $this->assertTrue($usuario->estaAnonimizado());
        $this->assertNotSame('0970009999', $usuario->telefono);
    }

    public function test_eliminar_la_cuenta_requiere_autenticacion(): void
    {
        $this->deleteJson('/api/v1/cuenta')->assertUnauthorized();
    }
}
