<?php

namespace Tests\Feature\Staffing;

use App\Models\Asignacion;
use App\Models\Imagen;
use App\Models\Profesional;
use App\Models\TipoImagen;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

class ProfesionalImagenTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El portafolio del profesional siempre se crea como 'muestra'
        // (forzado por el controller, no lo manda el cliente).
        TipoImagen::factory()->create(['codigo' => 'muestra', 'nombre' => 'Muestra']);
    }

    private function profesionalEmpleadoEnLocal(): array
    {
        [$usuario, $token, , $local] = $this->propietarioConLocal();
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);

        return [$usuario, $token, $profesional];
    }

    public function test_el_dueño_puede_agregar_una_imagen_al_portafolio(): void
    {
        [, $token, $profesional] = $this->profesionalEmpleadoEnLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/imagenes", ['url' => 'https://x.com/corte.jpg'])
            ->assertCreated()
            ->assertJsonPath('tipo', 'muestra')
            ->assertJsonPath('objeto_type', 'profesional')
            ->assertJsonPath('orden', 0);
    }

    public function test_eliminar_una_imagen_la_borra_de_verdad(): void
    {
        [, $token, $profesional] = $this->profesionalEmpleadoEnLocal();
        $imagen = Imagen::factory()->paraProfesional($profesional)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/imagenes/{$imagen->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('imagen', ['id' => $imagen->id]);
    }

    public function test_un_extraño_no_puede_agregar_imagenes(): void
    {
        $profesional = Profesional::factory()->create();
        Asignacion::factory()->create(['profesional_id' => $profesional->id]);

        $extraño = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$extraño->createToken('t')->plainTextToken}")
            ->postJson("/api/v1/profesionales/{$profesional->id}/imagenes", ['url' => 'https://x.com/a.jpg'])
            ->assertForbidden();
    }
}
