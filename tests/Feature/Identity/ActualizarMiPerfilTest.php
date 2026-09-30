<?php

namespace Tests\Feature\Identity;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autogestión del propio perfil (§4.3): `nombre`/`género`/`fecha_nacimiento`/
 * foto — `telefono`/`email` no se editan por acá, cada uno tiene su propio
 * flujo de verificación.
 */
class ActualizarMiPerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_actualiza_nombre_genero_y_fecha_de_nacimiento(): void
    {
        $usuario = Usuario::factory()->create(['nombre' => 'Nombre Viejo']);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/cuenta/perfil', [
                'nombre' => 'Nombre Nuevo', 'genero' => 'otro', 'fecha_nacimiento' => '1995-05-20',
            ])
            ->assertOk()
            ->assertJsonPath('nombre', 'Nombre Nuevo')
            ->assertJsonPath('genero', 'otro')
            ->assertJsonPath('fecha_nacimiento', '1995-05-20');

        $this->assertDatabaseHas('usuario', ['id' => $usuario->id, 'nombre' => 'Nombre Nuevo', 'genero' => 'otro']);
    }

    public function test_actualiza_solo_el_campo_que_se_manda(): void
    {
        $usuario = Usuario::factory()->create(['nombre' => 'Se Queda Igual', 'genero' => 'f']);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/cuenta/perfil', ['genero' => 'no_decir'])
            ->assertOk()
            ->assertJsonPath('nombre', 'Se Queda Igual')
            ->assertJsonPath('genero', 'no_decir');
    }

    public function test_establece_la_foto_de_perfil(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/cuenta/perfil', ['foto_url' => 'https://cdn.example.com/foto.jpg'])
            ->assertOk()
            ->assertJsonPath('foto_url', 'https://cdn.example.com/foto.jpg');
    }

    public function test_quitar_la_foto_con_null(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/cuenta/perfil', ['foto_url' => 'https://cdn.example.com/foto.jpg']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/cuenta/perfil', ['foto_url' => null])
            ->assertOk()
            ->assertJsonPath('foto_url', null);
    }

    public function test_rechaza_un_genero_invalido(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/cuenta/perfil', ['genero' => 'lo-que-sea'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genero');
    }

    public function test_rechaza_una_fecha_de_nacimiento_futura(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/cuenta/perfil', ['fecha_nacimiento' => now()->addYear()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_nacimiento');
    }

    public function test_no_se_puede_editar_telefono_ni_email_por_aqui(): void
    {
        $usuario = Usuario::factory()->create(['telefono' => '0991112222', 'email' => 'original@example.com']);
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/cuenta/perfil', [
                'nombre' => 'Igual', 'telefono' => '0999999999', 'email' => 'otro@example.com',
            ])
            ->assertOk();

        $this->assertDatabaseHas('usuario', [
            'id' => $usuario->id, 'telefono' => '0991112222', 'email' => 'original@example.com',
        ]);
    }
}
