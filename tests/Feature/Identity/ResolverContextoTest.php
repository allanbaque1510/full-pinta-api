<?php

namespace Tests\Feature\Identity;

use App\Models\Asignacion;
use App\Models\Local;
use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un mismo teléfono puede ser cliente, dueño de un negocio y barbero en otro
 * local, todo a la vez (§3.2) — el rol no es un campo de `usuario`.
 */
class ResolverContextoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_sin_membresias_ni_perfil_profesional_no_requiere_seleccion(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/contexto')
            ->assertOk()
            ->assertJsonPath('es_cliente', true)
            ->assertJsonPath('requiere_seleccion', false)
            ->assertJsonPath('contextos', []);
    }

    public function test_un_dueño_ve_su_negocio_como_contexto(): void
    {
        $usuario = Usuario::factory()->create();
        $negocio = Negocio::factory()->create(['propietario_id' => $usuario->id, 'nombre_marca' => 'Barbería Kevin']);
        NegocioMiembro::factory()->propietario()->create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $negocio->id,
            'local_id' => null,
        ]);

        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/contexto')
            ->assertOk()
            ->assertJsonPath('requiere_seleccion', false)
            ->assertJsonCount(1, 'contextos')
            ->assertJsonPath('contextos.0.tipo', 'negocio')
            ->assertJsonPath('contextos.0.rol', 'propietario')
            ->assertJsonPath('contextos.0.negocio_nombre', 'Barbería Kevin')
            ->assertJsonPath('contextos.0.local_id', null);
    }

    public function test_un_usuario_con_negocio_y_perfil_profesional_ve_ambos_y_requiere_seleccion(): void
    {
        $usuario = Usuario::factory()->create();

        $negocio = Negocio::factory()->create(['propietario_id' => $usuario->id]);
        NegocioMiembro::factory()->propietario()->create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $negocio->id,
            'local_id' => null,
        ]);

        $local = Local::factory()->create(['nombre' => 'Alborada']);
        $profesional = Profesional::factory()->conCuenta()->create(['usuario_id' => $usuario->id]);
        Asignacion::factory()->create([
            'local_id' => $local->id,
            'profesional_id' => $profesional->id,
            'rol' => 'barbero',
        ]);

        $token = $usuario->createToken('t')->plainTextToken;

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/contexto')
            ->assertOk()
            ->assertJsonPath('requiere_seleccion', true)
            ->assertJsonCount(2, 'contextos');

        $tipos = collect($respuesta->json('contextos'))->pluck('tipo')->sort()->values()->all();
        $this->assertSame(['negocio', 'profesional'], $tipos);
    }

    public function test_una_asignacion_vencida_no_aparece_como_contexto(): void
    {
        $usuario = Usuario::factory()->create();
        $local = Local::factory()->create();
        $profesional = Profesional::factory()->conCuenta()->create(['usuario_id' => $usuario->id]);

        Asignacion::factory()->terminada()->create([
            'local_id' => $local->id,
            'profesional_id' => $profesional->id,
        ]);

        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/contexto')
            ->assertJsonPath('contextos', []);
    }
}
