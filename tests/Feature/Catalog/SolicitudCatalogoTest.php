<?php

namespace Tests\Feature\Catalog;

use App\Models\Usuario;
use App\Models\Vertical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Cómo un local pide que la plataforma agregue un servicio que falta (§4.5).
 */
class SolicitudCatalogoTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Vertical::factory()->create(['codigo' => 'barberia', 'nombre' => 'Barbería']);
    }

    public function test_el_propietario_puede_pedir_un_servicio_nuevo(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/solicitudes-catalogo", [
                'vertical' => 'barberia', 'nombre_propuesto' => 'Afeitado a navaja',
                'descripcion' => 'Con toalla caliente',
            ])
            ->assertCreated()
            ->assertJsonPath('estado', 'pendiente')
            ->assertJsonPath('nombre_propuesto', 'Afeitado a navaja');
    }

    public function test_un_recepcionista_no_puede_pedir_servicios(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/solicitudes-catalogo", [
                'vertical' => 'barberia', 'nombre_propuesto' => 'X',
            ])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_listar_las_solicitudes(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/solicitudes-catalogo")
            ->assertForbidden();
    }
}
