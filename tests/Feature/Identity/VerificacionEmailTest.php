<?php

namespace Tests\Feature\Identity;

use App\Models\Usuario;
use App\Modules\Identity\Application\FakeEnviadorCodigoEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verificación de propiedad del email (§13.1): el `UNIQUE` de `usuario.email`
 * evita duplicados, no prueba que quien lo escribió controla esa bandeja.
 */
class VerificacionEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeEnviadorCodigoEmail::reset();
    }

    public function test_solicitar_envia_un_codigo_al_correo_registrado(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'juan@example.com', 'email_verificado' => false]);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/cuenta/email/solicitar-verificacion')
            ->assertOk()
            ->assertJsonStructure(['mensaje', 'expira_en_minutos']);

        $this->assertNotNull(FakeEnviadorCodigoEmail::ultimoCodigoPara('juan@example.com'));
    }

    public function test_solicitar_sin_correo_registrado_falla(): void
    {
        $usuario = Usuario::factory()->create(['email' => null]);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/cuenta/email/solicitar-verificacion')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_solicitar_un_correo_ya_verificado_falla(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'ya@example.com', 'email_verificado' => true]);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/cuenta/email/solicitar-verificacion')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_confirmar_con_el_codigo_correcto_marca_el_email_verificado(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'confirmo@example.com', 'email_verificado' => false]);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/cuenta/email/solicitar-verificacion');
        $codigo = FakeEnviadorCodigoEmail::ultimoCodigoPara('confirmo@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/cuenta/email/verificar', ['codigo' => $codigo])
            ->assertOk()
            ->assertJsonPath('email_verificado', true);

        $this->assertTrue($usuario->fresh()->email_verificado);
    }

    public function test_confirmar_con_codigo_incorrecto_falla(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'malcodigo@example.com', 'email_verificado' => false]);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/cuenta/email/solicitar-verificacion');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/cuenta/email/verificar', ['codigo' => '000000'])
            ->assertUnprocessable();

        $this->assertFalse($usuario->fresh()->email_verificado);
    }
}
