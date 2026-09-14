<?php

namespace Tests\Feature\Identity;

use App\Models\Cita;
use App\Models\ClienteLocal;
use App\Models\Usuario;
use App\Modules\Identity\Application\FakeEnviadorOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El caso que el §4.3 marca como crítico: la recepción agenda un walk-in con
 * solo nombre y teléfono (cliente sombra, `password_hash IS NULL`). Cuando esa
 * persona se registra en la app con el mismo teléfono, reclama el registro por
 * OTP y HEREDA su historial — porque sigue siendo la misma fila, no una nueva.
 */
class ClienteSombraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeEnviadorOtp::reset();
    }

    public function test_reclamar_un_cliente_sombra_conserva_el_mismo_id_y_su_historial(): void
    {
        $sombra = Usuario::factory()->sombra()->create([
            'telefono' => '0981112222',
            'nombre' => 'Cliente Walk-in',
        ]);

        $citaPrevia = Cita::factory()->completada()->create(['cliente_id' => $sombra->id]);
        $clienteLocal = ClienteLocal::factory()->create([
            'usuario_id' => $sombra->id,
            'local_id' => $citaPrevia->local_id,
            'total_citas' => 3,
        ]);

        $this->assertTrue($sombra->esClienteSombra());

        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $sombra->telefono]);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara($sombra->telefono);

        $respuesta = $this->postJson('/api/v1/auth/otp/verificar', [
            'telefono' => $sombra->telefono,
            'codigo' => $codigo,
        ])->assertCreated();

        // Mismo id: no se creó una cuenta nueva ni duplicada.
        $this->assertSame($sombra->id, $respuesta->json('usuario.id'));
        $this->assertSame(1, Usuario::where('telefono', $sombra->telefono)->count());

        $sombra->refresh();
        $this->assertFalse($sombra->esClienteSombra());
        $this->assertTrue($sombra->telefono_verificado);

        // El historial sigue intacto, colgado del mismo usuario_id.
        $this->assertDatabaseHas('cita', ['id' => $citaPrevia->id, 'cliente_id' => $sombra->id]);
        $this->assertSame(3, $clienteLocal->fresh()->total_citas);
    }

    public function test_reclamar_conserva_el_nombre_que_puso_la_recepcion_si_no_se_manda_otro(): void
    {
        $sombra = Usuario::factory()->sombra()->create([
            'telefono' => '0982223333',
            'nombre' => 'Cliente Walk-in',
        ]);

        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $sombra->telefono]);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara($sombra->telefono);

        $this->postJson('/api/v1/auth/otp/verificar', [
            'telefono' => $sombra->telefono,
            'codigo' => $codigo,
        ])->assertJsonPath('usuario.nombre', 'Cliente Walk-in');
    }

    public function test_reclamar_registra_el_consentimiento_de_operacion(): void
    {
        $sombra = Usuario::factory()->sombra()->create(['telefono' => '0983334444']);

        $this->postJson('/api/v1/auth/otp/solicitar', ['telefono' => $sombra->telefono]);
        $codigo = FakeEnviadorOtp::ultimoCodigoPara($sombra->telefono);

        $this->postJson('/api/v1/auth/otp/verificar', [
            'telefono' => $sombra->telefono,
            'codigo' => $codigo,
        ]);

        $this->assertDatabaseHas('consentimiento', [
            'usuario_id' => $sombra->id,
            'finalidad' => 'operacion_servicio',
        ]);
    }
}
