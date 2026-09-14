<?php

namespace Tests\Feature\Identity;

use App\Models\Cita;
use App\Models\Usuario;
use App\Modules\Identity\Application\AnonimizarUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Derecho de eliminación (§13.1). No se borra la fila —§4.2: "no se borran
 * locales, profesionales ni citas"— porque el historial de citas cuelga de
 * este `usuario_id` y tiene que seguir cuadrando.
 */
class AnonimizarUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonimizar_borra_los_datos_identificables_pero_conserva_el_id(): void
    {
        $usuario = Usuario::factory()->create([
            'telefono' => '0970001111',
            'nombre' => 'Persona Real',
            'email' => 'persona@ejemplo.com',
        ]);
        $id = $usuario->id;

        (new AnonimizarUsuario)($usuario);
        $usuario->refresh();

        $this->assertSame($id, $usuario->id);
        $this->assertNotSame('0970001111', $usuario->telefono);
        $this->assertNull($usuario->email);
        $this->assertNotSame('Persona Real', $usuario->nombre);
        $this->assertNotNull($usuario->anonimizado_at);
        $this->assertTrue($usuario->estaAnonimizado());
    }

    public function test_anonimizar_no_descuadra_el_historial_de_citas(): void
    {
        $usuario = Usuario::factory()->create();
        $cita = Cita::factory()->completada()->create(['cliente_id' => $usuario->id, 'precio_total' => 25]);

        (new AnonimizarUsuario)($usuario);

        $this->assertDatabaseHas('cita', [
            'id' => $cita->id,
            'cliente_id' => $usuario->id,
            'precio_total' => 25,
        ]);
    }

    public function test_anonimizar_revoca_todos_los_tokens_vigentes(): void
    {
        $usuario = Usuario::factory()->create();
        $usuario->createToken('a');
        $usuario->createToken('b');

        (new AnonimizarUsuario)($usuario);

        $this->assertCount(0, $usuario->fresh()->tokens);
    }

    public function test_un_telefono_anonimizado_no_puede_reclamarse_de_nuevo(): void
    {
        $usuario = Usuario::factory()->sombra()->create(['telefono' => '0970002222']);
        $telefonoOriginal = $usuario->telefono;

        (new AnonimizarUsuario)($usuario);

        $this->assertDatabaseMissing('usuario', ['telefono' => $telefonoOriginal]);
    }
}
