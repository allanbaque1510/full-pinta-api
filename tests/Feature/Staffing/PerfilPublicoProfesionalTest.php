<?php

namespace Tests\Feature\Staffing;

use App\Models\Favorito;
use App\Models\Habilidad;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perfil público del profesional (§7.5): visible sin cuenta, salvo que el
 * propio profesional lo haya ocultado (`perfil_publico = false`).
 */
class PerfilPublicoProfesionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_el_perfil_publico_de_un_profesional(): void
    {
        $profesional = Profesional::factory()->create(['nombre' => 'Kevin Barbero']);
        Habilidad::factory()->create(['profesional_id' => $profesional->id]);

        $this->getJson("/api/v1/profesionales/{$profesional->id}/perfil-publico")
            ->assertOk()
            ->assertJsonPath('id', $profesional->id)
            ->assertJsonPath('nombre', 'Kevin Barbero')
            ->assertJsonCount(1, 'servicios');
    }

    public function test_un_profesional_con_perfil_oculto_devuelve_404(): void
    {
        $profesional = Profesional::factory()->create(['perfil_publico' => false]);

        $this->getJson("/api/v1/profesionales/{$profesional->id}/perfil-publico")->assertNotFound();
    }

    public function test_es_favorito_es_false_sin_autenticacion(): void
    {
        $profesional = Profesional::factory()->create();

        $this->getJson("/api/v1/profesionales/{$profesional->id}/perfil-publico")
            ->assertJsonPath('es_favorito', false);
    }

    public function test_es_favorito_es_true_para_un_cliente_que_lo_marco(): void
    {
        $profesional = Profesional::factory()->create();
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;
        Favorito::factory()->deProfesional()->create(['usuario_id' => $usuario->id, 'profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/profesionales/{$profesional->id}/perfil-publico")
            ->assertJsonPath('es_favorito', true);
    }
}
