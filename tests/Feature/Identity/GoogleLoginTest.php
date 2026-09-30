<?php

namespace Tests\Feature\Identity;

use App\Models\Usuario;
use App\Modules\Identity\Application\FakeVerificadorTokenGoogle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login/registro con Google (§4.3): el teléfono sigue siendo obligatorio —
 * Google no lo da, el front lo pide en el mismo formulario.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeVerificadorTokenGoogle::reset();
    }

    public function test_un_google_id_nuevo_crea_la_cuenta_con_el_telefono_del_body_sin_verificar(): void
    {
        FakeVerificadorTokenGoogle::registrarToken('token-valido', [
            'sub' => 'google-sub-1', 'email' => 'ana@example.com', 'name' => 'Ana Pérez',
        ]);

        $respuesta = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'token-valido', 'telefono' => '0991112222',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('usuario.telefono', '0991112222')
            ->assertJsonPath('usuario.telefono_verificado', false)
            ->assertJsonPath('usuario.email', 'ana@example.com')
            // Google ya verificó este correo antes de emitir el token
            // (`email_verified` en el claim, `true` por defecto en el fake).
            ->assertJsonPath('usuario.email_verificado', true)
            ->assertJsonPath('usuario.nombre', 'Ana Pérez')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('usuario', [
            'google_id' => 'google-sub-1', 'telefono' => '0991112222', 'telefono_verificado' => false,
            'email_verificado' => true,
        ]);
    }

    public function test_un_google_id_nuevo_con_email_no_verificado_en_google_nace_sin_verificar(): void
    {
        FakeVerificadorTokenGoogle::registrarToken('token-sin-verificar', [
            'sub' => 'google-sub-sinverificar', 'email' => 'pendiente@example.com', 'name' => 'Pendiente',
            'email_verified' => false,
        ]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'token-sin-verificar', 'telefono' => '0991110000'])
            ->assertCreated()
            ->assertJsonPath('usuario.email_verificado', false);
    }

    public function test_un_email_que_ya_existe_por_otp_se_enlaza_en_vez_de_duplicarse(): void
    {
        $existente = Usuario::factory()->create(['email' => 'kevin@example.com', 'telefono' => '0993334444']);

        FakeVerificadorTokenGoogle::registrarToken('token-kevin', [
            'sub' => 'google-sub-kevin', 'email' => 'kevin@example.com', 'name' => 'Kevin',
        ]);

        // El teléfono del body se ignora: la cuenta ya tiene uno.
        $this->postJson('/api/v1/auth/google', ['id_token' => 'token-kevin', 'telefono' => '0999999999'])
            ->assertCreated()
            ->assertJsonPath('usuario.id', $existente->id)
            ->assertJsonPath('usuario.telefono', '0993334444');

        $this->assertSame(1, Usuario::where('email', 'kevin@example.com')->count());
        $this->assertDatabaseHas('usuario', ['id' => $existente->id, 'google_id' => 'google-sub-kevin']);
    }

    /** El token de Google ya prueba propiedad del correo: enlazar sube `email_verificado`, aunque antes fuera false. */
    public function test_enlazar_con_google_verifica_el_email_de_una_cuenta_que_no_lo_tenia_verificado(): void
    {
        $existente = Usuario::factory()->create([
            'email' => 'porverificar@example.com', 'telefono' => '0993335555', 'email_verificado' => false,
        ]);

        FakeVerificadorTokenGoogle::registrarToken('token-verifica', [
            'sub' => 'google-sub-verifica', 'email' => 'porverificar@example.com', 'name' => 'Alguien',
            'email_verified' => true,
        ]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'token-verifica', 'telefono' => '0999998888'])
            ->assertCreated()
            ->assertJsonPath('usuario.email_verificado', true);

        $this->assertTrue($existente->fresh()->email_verificado);
    }

    /** Un email ya verificado por otro medio nunca se degrada, aunque Google no lo confirme en este login puntual. */
    public function test_enlazar_con_google_no_degrada_un_email_ya_verificado(): void
    {
        $existente = Usuario::factory()->create([
            'email' => 'yaverificado@example.com', 'telefono' => '0993336666', 'email_verificado' => true,
        ]);

        FakeVerificadorTokenGoogle::registrarToken('token-nodegrada', [
            'sub' => 'google-sub-nodegrada', 'email' => 'yaverificado@example.com', 'name' => 'Alguien',
            'email_verified' => false,
        ]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'token-nodegrada', 'telefono' => '0999997777'])
            ->assertCreated()
            ->assertJsonPath('usuario.email_verificado', true);

        $this->assertTrue($existente->fresh()->email_verificado);
    }

    public function test_volver_a_iniciar_sesion_con_el_mismo_google_id_no_duplica_la_cuenta(): void
    {
        FakeVerificadorTokenGoogle::registrarToken('token-repetido', [
            'sub' => 'google-sub-2', 'email' => 'repetido@example.com', 'name' => 'Repetido',
        ]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'token-repetido', 'telefono' => '0995556666'])
            ->assertCreated();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'token-repetido', 'telefono' => '0997778888'])
            ->assertCreated()
            ->assertJsonPath('usuario.telefono', '0995556666'); // el segundo teléfono se ignora

        $this->assertSame(1, Usuario::where('google_id', 'google-sub-2')->count());
    }

    public function test_un_telefono_ya_usado_por_otra_cuenta_rechaza_la_creacion(): void
    {
        Usuario::factory()->create(['telefono' => '0991112222']);

        FakeVerificadorTokenGoogle::registrarToken('token-choque', [
            'sub' => 'google-sub-choque', 'email' => 'choque@example.com', 'name' => 'Choque',
        ]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'token-choque', 'telefono' => '0991112222'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telefono');
    }

    public function test_un_token_invalido_rechaza_el_login(): void
    {
        $this->postJson('/api/v1/auth/google', ['id_token' => 'token-inexistente', 'telefono' => '0991112222'])
            ->assertUnauthorized()
            ->assertJsonPath('codigo', 'credencial_google_invalida');
    }
}
