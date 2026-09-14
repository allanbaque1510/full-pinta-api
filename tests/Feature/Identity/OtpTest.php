<?php

namespace Tests\Feature\Identity;

use App\Models\Otp;
use App\Models\Usuario;
use App\Modules\Identity\Application\FakeEnviadorOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Registro e inicio de sesión son el MISMO endpoint (§4.3): no hay contraseña
 * en ningún punto del flujo del cliente, solo teléfono + OTP.
 */
class OtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeEnviadorOtp::reset();
    }

    public function test_solicitar_otp_crea_un_registro_pendiente_y_lo_envia(): void
    {
        $telefono = '0991112222';

        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $telefono])
            ->assertOk()
            ->assertJsonStructure(['mensaje', 'expira_en_minutos']);

        $this->assertDatabaseHas('otp', ['telefono' => $telefono]);
        $this->assertNotNull(FakeEnviadorOtp::ultimoCodigoPara($telefono));
    }

    public function test_solicitar_otp_rechaza_un_telefono_con_formato_invalido(): void
    {
        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telefono');
    }

    public function test_un_telefono_nuevo_se_registra_al_verificar_con_nombre(): void
    {
        $telefono = '0992223333';
        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $telefono]);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara($telefono);

        $respuesta = $this->postJson('/api/v1/auth/otp/verificar', [
            'telefono' => $telefono,
            'codigo' => $codigo,
            'nombre' => 'Ana Pérez',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('usuario.telefono', $telefono)
            ->assertJsonPath('usuario.nombre', 'Ana Pérez')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('usuario', ['telefono' => $telefono, 'telefono_verificado' => true]);
        $this->assertDatabaseHas('consentimiento', [
            'usuario_id' => $respuesta->json('usuario.id'),
            'finalidad' => 'operacion_servicio',
            'otorgado' => true,
        ]);
    }

    public function test_un_telefono_nuevo_sin_nombre_no_puede_registrarse(): void
    {
        $telefono = '0993334444';
        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $telefono]);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara($telefono);

        $this->postJson('/api/v1/auth/otp/verificar', ['telefono' => $telefono, 'codigo' => $codigo])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nombre');

        $this->assertDatabaseMissing('usuario', ['telefono' => $telefono]);
    }

    public function test_un_codigo_incorrecto_no_verifica_y_cuenta_como_intento(): void
    {
        $telefono = '0994445555';
        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $telefono]);

        $this->postJson('/api/v1/auth/otp/verificar', [
            'telefono' => $telefono,
            'codigo' => '000000',
            'nombre' => 'Quien sea',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'otp_incorrecto')
            ->assertJsonPath('intentos_restantes', 4);

        $this->assertSame(1, Otp::where('telefono', $telefono)->value('intentos'));
    }

    public function test_un_codigo_expirado_no_verifica(): void
    {
        $telefono = '0995556666';
        Otp::factory()->conCodigo('654321')->expirado()->create(['telefono' => $telefono]);

        $this->postJson('/api/v1/auth/otp/verificar', [
            'telefono' => $telefono,
            'codigo' => '654321',
            'nombre' => 'Quien sea',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');
    }

    public function test_un_usuario_ya_registrado_solo_inicia_sesion_sin_crear_otro(): void
    {
        $usuario = Usuario::factory()->create(['telefono' => '0996667777']);

        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $usuario->telefono]);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara($usuario->telefono);

        $this->postJson('/api/v1/auth/otp/verificar', [
            'telefono' => $usuario->telefono,
            'codigo' => $codigo,
        ])
            ->assertCreated()
            ->assertJsonPath('usuario.id', $usuario->id);

        $this->assertSame(1, Usuario::where('telefono', $usuario->telefono)->count());
    }

    public function test_solicitar_otp_respeta_el_limite_por_ventana(): void
    {
        $telefono = '0997778888';
        RateLimiter::clear("otp:{$telefono}");

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $telefono])->assertOk();
        }

        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $telefono])
            ->assertStatus(429)
            ->assertJsonPath('codigo', 'otp_demasiadas_solicitudes')
            ->assertHeader('Retry-After');
    }

    public function test_un_token_autentica_contra_una_ruta_protegida(): void
    {
        $telefono = '0998889999';
        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $telefono]);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara($telefono);

        $token = $this->postJson('/api/v1/auth/otp/verificar', [
            'telefono' => $telefono,
            'codigo' => $codigo,
            'nombre' => 'Alguien',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/contexto')
            ->assertOk()
            ->assertJsonPath('es_cliente', true);
    }

    /**
     * Propietario y recepción suelen necesitar su teléfono personal Y la
     * tablet del mostrador abiertos a la vez — por eso el límite es 2, no 1
     * (`fullpinta.max_dispositivos_activos`). Al entrar en un tercer
     * dispositivo, se cierra el más antiguo, nunca se rechaza el login nuevo.
     */
    public function test_iniciar_sesion_en_un_tercer_dispositivo_cierra_el_mas_antiguo(): void
    {
        $telefono = '0999990001';

        $login = function () use ($telefono) {
            $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $telefono]);
            $codigo = FakeEnviadorOtp::ultimoCodigoPara($telefono);

            return $this->postJson('/api/v1/auth/otp/verificar', [
                'telefono' => $telefono, 'codigo' => $codigo, 'nombre' => 'Kevin',
            ])->json('token');
        };

        $telefonoDelDueño = $login();   // dispositivo 1
        $tabletDelMostrador = $login(); // dispositivo 2
        $celularNuevo = $login();       // dispositivo 3: entra, el 1 se cierra

        $this->withHeader('Authorization', "Bearer {$telefonoDelDueño}")
            ->getJson('/api/v1/auth/contexto')
            ->assertUnauthorized();

        $this->withHeader('Authorization', "Bearer {$tabletDelMostrador}")
            ->getJson('/api/v1/auth/contexto')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$celularNuevo}")
            ->getJson('/api/v1/auth/contexto')
            ->assertOk();
    }

    public function test_cerrar_sesion_revoca_solo_el_token_actual(): void
    {
        $usuario = Usuario::factory()->create();
        $tokenA = $usuario->createToken('a')->plainTextToken;
        $usuario->createToken('b');

        $this->assertCount(2, $usuario->tokens);

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertCount(1, $usuario->fresh()->tokens);
    }
}
