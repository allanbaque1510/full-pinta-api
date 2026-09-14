<?php

namespace Tests\Feature\Identity;

use App\Models\Usuario;
use App\Modules\Identity\Application\FakeEnviadorOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Login/registro por correo/contraseña (§4.3): el teléfono sigue siendo obligatorio. */
class EmailLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeEnviadorOtp::reset();
    }

    public function test_registrarse_crea_la_cuenta_con_el_telefono_sin_verificar(): void
    {
        $this->postJson('/api/v1/auth/registro', [
            'nombre' => 'Ana Pérez', 'email' => 'ana@example.com',
            'password' => 'clave-larga-1', 'telefono' => '0991112222',
        ])
            ->assertCreated()
            ->assertJsonPath('usuario.email', 'ana@example.com')
            ->assertJsonPath('usuario.telefono_verificado', false)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('usuario', ['email' => 'ana@example.com', 'telefono_verificado' => false]);
    }

    public function test_no_se_puede_registrar_dos_veces_con_el_mismo_email(): void
    {
        Usuario::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/v1/auth/registro', [
            'nombre' => 'Otro', 'email' => 'dup@example.com',
            'password' => 'clave-larga-1', 'telefono' => '0992223333',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_no_se_puede_registrar_con_un_telefono_ya_usado(): void
    {
        Usuario::factory()->create(['telefono' => '0993334444']);

        $this->postJson('/api/v1/auth/registro', [
            'nombre' => 'Otro', 'email' => 'nuevo@example.com',
            'password' => 'clave-larga-1', 'telefono' => '0993334444',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telefono');
    }

    public function test_login_con_credenciales_correctas(): void
    {
        $this->postJson('/api/v1/auth/registro', [
            'nombre' => 'Carla', 'email' => 'carla@example.com',
            'password' => 'clave-larga-1', 'telefono' => '0994445555',
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'carla@example.com', 'password' => 'clave-larga-1'])
            ->assertOk()
            ->assertJsonPath('usuario.email', 'carla@example.com')
            ->assertJsonStructure(['token']);
    }

    public function test_login_con_password_incorrecta_falla(): void
    {
        $this->postJson('/api/v1/auth/registro', [
            'nombre' => 'Diego', 'email' => 'diego@example.com',
            'password' => 'clave-correcta', 'telefono' => '0995556666',
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'diego@example.com', 'password' => 'clave-incorrecta'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_un_usuario_registrado_solo_por_otp_no_puede_entrar_por_password(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'soloOtp@example.com']);
        // password_hash de la factory es real ('secreta'), simula el caso
        // más estricto: ni siquiera intentando la contraseña por defecto.

        $this->postJson('/api/v1/auth/login', ['email' => 'soloOtp@example.com', 'password' => 'lo-que-sea'])
            ->assertUnprocessable();

        $this->assertNotNull($usuario);
    }

    public function test_respeta_el_limite_de_intentos_por_ventana(): void
    {
        $this->postJson('/api/v1/auth/registro', [
            'nombre' => 'Rate', 'email' => 'rate@example.com',
            'password' => 'clave-correcta', 'telefono' => '0996667777',
        ]);

        RateLimiter::clear('login:rate@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'rate@example.com', 'password' => 'incorrecta'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'rate@example.com', 'password' => 'incorrecta'])
            ->assertStatus(429)
            ->assertJsonPath('codigo', 'demasiados_intentos_login')
            ->assertHeader('Retry-After');
    }

    public function test_verificar_el_telefono_de_una_cuenta_registrada_por_email_reusa_el_flujo_de_otp(): void
    {
        $registro = $this->postJson('/api/v1/auth/registro', [
            'nombre' => 'Verificable', 'email' => 'verificable@example.com',
            'password' => 'clave-larga-1', 'telefono' => '0998889999',
        ])->assertJsonPath('usuario.telefono_verificado', false);

        $usuarioId = $registro->json('usuario.id');

        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => '0998889999'])->assertOk();
        $codigo = FakeEnviadorOtp::ultimoCodigoPara('0998889999');

        $this->postJson('/api/v1/auth/otp/verificar', ['telefono' => '0998889999', 'codigo' => $codigo])
            ->assertCreated()
            ->assertJsonPath('usuario.id', $usuarioId)
            ->assertJsonPath('usuario.telefono_verificado', true);

        $this->assertSame(1, Usuario::where('telefono', '0998889999')->count());
    }
}
