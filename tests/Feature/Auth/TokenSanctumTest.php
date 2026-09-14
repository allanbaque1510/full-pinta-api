<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los tokens son por usuario, como en cualquier app con Sanctum: la tabla
 * `personal_access_tokens` apunta al usuario por `tokenable_type` +
 * `tokenable_id`.
 *
 * Estos tests crean los tokens directo con `$usuario->createToken(...)`, sin
 * pasar por `VerificarOtp` — prueban lo que Sanctum permite a nivel de
 * modelo, no la regla del producto. El límite de sesiones simultáneas
 * (`fullpinta.max_dispositivos_activos`, hoy 2) lo aplica `VerificarOtp`, no
 * el modelo: ver `OtpTest::test_iniciar_sesion_en_un_tercer_dispositivo_cierra_el_mas_antiguo`.
 *
 * La trampa que cubre este test: la migración del paquete crea `tokenable_id`
 * como `bigint`, y nuestras claves son `uuid` (§4.2).
 */
class TokenSanctumTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_con_id_uuid_puede_emitir_un_token(): void
    {
        $usuario = $this->crearUsuario('0990001111');

        $token = $usuario->createToken('app-movil');

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $usuario->id,
            'tokenable_type' => Usuario::class,
            'name' => 'app-movil',
        ]);
    }

    public function test_el_token_autentica_contra_una_ruta_protegida(): void
    {
        $usuario = $this->crearUsuario('0990002222');
        $token = $usuario->createToken('app-movil')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('id', $usuario->id)
            ->assertJsonPath('telefono', '0990002222');
    }

    public function test_el_hash_de_la_contrasena_nunca_se_serializa(): void
    {
        $usuario = $this->crearUsuario('0990003333');
        $token = $usuario->createToken('app-movil')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonMissingPath('password_hash');
    }

    public function test_un_usuario_puede_tener_varios_tokens_a_la_vez(): void
    {
        $usuario = $this->crearUsuario('0990004444');

        $usuario->createToken('telefono');
        $usuario->createToken('tablet-del-local');

        $this->assertSame(2, DB::table('personal_access_tokens')
            ->where('tokenable_id', $usuario->id)->count());
    }

    public function test_revocar_un_token_deja_de_autenticar(): void
    {
        $usuario = $this->crearUsuario('0990005555');
        $token = $usuario->createToken('app-movil');
        $plano = $token->plainTextToken;

        $token->accessToken->delete();

        $this->withHeader('Authorization', 'Bearer '.$plano)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    /**
     * Cliente sombra: lo creó la recepción para un walk-in y no tiene
     * contraseña. Reclamará la cuenta por OTP con el mismo teléfono (§4.3).
     */
    public function test_un_cliente_sombra_se_reconoce_como_tal(): void
    {
        $sombra = $this->crearUsuario('0990006666');
        $this->assertTrue($sombra->esClienteSombra());

        $sombra->update(['password_hash' => bcrypt('secreta')]);
        $this->assertFalse($sombra->fresh()->esClienteSombra());
    }

    private function crearUsuario(string $telefono): Usuario
    {
        return Usuario::create([
            'telefono' => $telefono,
            'nombre' => 'Prueba',
        ]);
    }
}
