<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Profesional;
use App\Models\ProfesionalFoto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

class ProfesionalFotoTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    private function profesionalEmpleadoEnLocal(): array
    {
        [$usuario, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        return [$usuario, $token, $profesional];
    }

    public function test_el_dueño_puede_agregar_una_foto_al_portafolio(): void
    {
        [, $token, $profesional] = $this->profesionalEmpleadoEnLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/fotos", ['url' => 'https://x.com/corte.jpg'])
            ->assertCreated()
            ->assertJsonPath('orden', 0);
    }

    public function test_eliminar_una_foto_la_borra_de_verdad(): void
    {
        [, $token, $profesional] = $this->profesionalEmpleadoEnLocal();
        $foto = ProfesionalFoto::factory()->create(['profesional_id' => $profesional->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/profesionales/{$profesional->id}/fotos/{$foto->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('profesional_foto', ['id' => $foto->id]);
    }

    public function test_un_extraño_no_puede_agregar_fotos(): void
    {
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['profesional_id' => $profesional->id]);

        $extraño = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$extraño->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/fotos", ['url' => 'https://x.com/a.jpg'])
            ->assertForbidden();
    }
}
