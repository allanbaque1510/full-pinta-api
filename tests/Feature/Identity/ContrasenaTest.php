<?php

namespace Tests\Feature\Identity;

use App\Models\Usuario;
use App\Modules\Identity\Application\FakeEnviadorCodigoEmail;
use App\Modules\Identity\Application\FakeEnviadorOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recuperar contraseña (§4.3): dos canales, teléfono por WhatsApp o correo —
 * mismo mecanismo de código de un solo uso que `otp`, generalizado. Cambiar
 * contraseña conociendo la actual es un flujo aparte, más simple.
 */
class ContrasenaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeEnviadorOtp::reset();
        FakeEnviadorCodigoEmail::reset();
    }

    public function test_solicitar_por_whatsapp_envia_un_codigo_al_telefono(): void
    {
        $usuario = Usuario::factory()->create(['telefono' => '0991110001']);

        $this->postJson('/api/v1/auth/contrasena/olvide', [
            'canal' => 'whatsapp', 'telefono' => $usuario->telefono,
        ])
            ->assertOk()
            ->assertJsonStructure(['mensaje', 'expira_en_minutos']);

        $this->assertNotNull(FakeEnviadorOtp::ultimoCodigoPara($usuario->telefono));
    }

    public function test_solicitar_por_email_envia_un_codigo_al_correo(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'recupera@example.com']);

        $this->postJson('/api/v1/auth/contrasena/olvide', [
            'canal' => 'email', 'email' => $usuario->email,
        ])->assertOk();

        $this->assertNotNull(FakeEnviadorCodigoEmail::ultimoCodigoPara($usuario->email));
    }

    public function test_solicitar_responde_igual_aunque_el_destino_no_exista(): void
    {
        $this->postJson('/api/v1/auth/contrasena/olvide', [
            'canal' => 'whatsapp', 'telefono' => '0999999999',
        ])->assertOk();

        $this->postJson('/api/v1/auth/contrasena/olvide', [
            'canal' => 'email', 'email' => 'nadie@example.com',
        ])->assertOk();
    }

    public function test_solicitar_exige_el_campo_del_canal_elegido(): void
    {
        $this->postJson('/api/v1/auth/contrasena/olvide', ['canal' => 'whatsapp'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telefono');

        $this->postJson('/api/v1/auth/contrasena/olvide', ['canal' => 'email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_restablecer_por_whatsapp_fija_la_nueva_contrasena_y_autentica(): void
    {
        $usuario = Usuario::factory()->create(['telefono' => '0992220002']);
        $tokenViejo = $usuario->createToken('viejo')->plainTextToken;

        $this->postJson('/api/v1/auth/contrasena/olvide', ['canal' => 'whatsapp', 'telefono' => $usuario->telefono]);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara($usuario->telefono);

        $respuesta = $this->postJson('/api/v1/auth/contrasena/restablecer', [
            'canal' => 'whatsapp', 'telefono' => $usuario->telefono, 'codigo' => $codigo, 'password' => 'clave-nueva-1',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonStructure(['token']);

        $this->assertNotSame($tokenViejo, $respuesta->json('token'));

        // La sesión anterior queda revocada (recuperar acceso implica no
        // saber quién más pudo quedar autenticado).
        $this->withHeader('Authorization', "Bearer {$tokenViejo}")
            ->getJson('/api/v1/auth/contexto')
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', ['email' => $usuario->email, 'password' => 'clave-nueva-1'])
            ->assertOk();
    }

    public function test_restablecer_por_email_fija_la_contrasena_y_marca_el_email_verificado(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'marca@example.com', 'email_verificado' => false]);

        $this->postJson('/api/v1/auth/contrasena/olvide', ['canal' => 'email', 'email' => $usuario->email]);
        $codigo = FakeEnviadorCodigoEmail::ultimoCodigoPara($usuario->email);

        $this->postJson('/api/v1/auth/contrasena/restablecer', [
            'canal' => 'email', 'email' => $usuario->email, 'codigo' => $codigo, 'password' => 'clave-nueva-1',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.email_verificado', true);

        $this->assertTrue($usuario->fresh()->email_verificado);
    }

    public function test_restablecer_con_codigo_incorrecto_falla(): void
    {
        $usuario = Usuario::factory()->create(['telefono' => '0993330003']);
        $this->postJson('/api/v1/auth/contrasena/olvide', ['canal' => 'whatsapp', 'telefono' => $usuario->telefono]);

        $this->postJson('/api/v1/auth/contrasena/restablecer', [
            'canal' => 'whatsapp', 'telefono' => $usuario->telefono, 'codigo' => '000000', 'password' => 'clave-nueva-1',
        ])->assertUnprocessable();
    }

    public function test_restablecer_para_un_destino_sin_cuenta_no_revela_nada(): void
    {
        $this->postJson('/api/v1/auth/contrasena/olvide', ['canal' => 'whatsapp', 'telefono' => '0994440004']);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara('0994440004');

        $this->postJson('/api/v1/auth/contrasena/restablecer', [
            'canal' => 'whatsapp', 'telefono' => '0994440004', 'codigo' => $codigo, 'password' => 'clave-nueva-1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');
    }

    public function test_cambiar_contrasena_con_la_actual_correcta(): void
    {
        $usuario = Usuario::factory()->create(); // password_hash = 'secreta'
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/cuenta/contrasena', ['actual' => 'secreta', 'nueva' => 'clave-nueva-1'])
            ->assertNoContent();

        $this->postJson('/api/v1/auth/login', ['email' => $usuario->email, 'password' => 'clave-nueva-1'])
            ->assertOk();
    }

    public function test_cambiar_contrasena_con_la_actual_incorrecta_falla(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/cuenta/contrasena', ['actual' => 'lo-que-sea', 'nueva' => 'clave-nueva-1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('actual');
    }
}
