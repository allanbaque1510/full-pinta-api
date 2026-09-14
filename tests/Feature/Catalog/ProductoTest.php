<?php

namespace Tests\Feature\Catalog;

use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaNegocioDePrueba;
use Tests\TestCase;

/**
 * Necesarios para que la liquidación de comisiones sea correcta: llevan un
 * porcentaje distinto (o cero) al de un servicio (§4.5).
 */
class ProductoTest extends TestCase
{
    use CreaNegocioDePrueba, RefreshDatabase;

    public function test_el_propietario_puede_dar_de_alta_un_producto(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/productos", [
                'nombre' => 'Pomada', 'precio' => 12.5, 'comision_pct' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('nombre', 'Pomada')
            ->assertJsonPath('activo', true);
    }

    public function test_sin_comision_pct_nace_en_cero_no_en_null(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        // DEFAULT en Postgres, no en PHP: si el servicio no lo fija
        // explícito, el modelo recién creado devuelve `null` en vez de 0.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/productos", ['nombre' => 'Shampoo', 'precio' => 8])
            ->assertCreated()
            ->assertJsonPath('comision_pct', '0.00');
    }

    public function test_desactivar_un_producto_no_lo_borra(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();
        $producto = Producto::factory()->create(['local_id' => $local->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/productos/{$producto->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('producto', ['id' => $producto->id, 'activo' => false]);
    }

    public function test_rechaza_una_comision_fuera_de_rango(): void
    {
        [, $token, , $local] = $this->propietarioConLocal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/productos", [
                'nombre' => 'X', 'precio' => 5, 'comision_pct' => 150,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comision_pct');
    }

    public function test_un_recepcionista_no_puede_crear_productos(): void
    {
        [, , $negocio, $local] = $this->propietarioConLocal();
        [, $token] = $this->recepcionEnNegocio($negocio);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/locales/{$local->id}/productos", ['nombre' => 'X', 'precio' => 5])
            ->assertForbidden();
    }

    public function test_un_extrano_no_puede_listar_los_productos(): void
    {
        [, , , $local] = $this->propietarioConLocal();
        $otro = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$otro->createToken('t')->plainTextToken}")
            ->getJson("/api/v1/locales/{$local->id}/productos")
            ->assertForbidden();
    }
}
