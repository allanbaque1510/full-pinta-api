<?php

namespace Database\Factories;

use App\Models\Local;
use App\Models\Negocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Local>
 *
 * `ubicacion` pasa por el cast `App\Casts\Ubicacion`: basta con dar
 * `['lat' => .., 'lng' => ..]`, él arma el `ST_GeogFromText(...)`. Las
 * coordenadas caen en Guayaquil, que es donde arranca el producto.
 */
class LocalFactory extends Factory
{
    protected $model = Local::class;

    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'nombre' => 'Sucursal '.fake()->citySuffix(),
            'direccion' => fake()->address(),
            'referencia' => fake()->optional()->sentence(4),
            'ubicacion' => [
                'lat' => fake()->randomFloat(6, -2.22, -2.08),
                'lng' => fake()->randomFloat(6, -79.95, -79.85),
            ],
            'telefono' => '04'.fake()->numerify('#######'),
            'whatsapp' => '09'.fake()->numerify('########'),
            'verificado' => false,
            'estado' => 'activo',
            'lead_time_min' => 60,
            'horizonte_dias' => 30,
            'politica_cancelacion_horas' => 2,
        ];
    }

    public function borrador(): static
    {
        return $this->state(fn () => ['estado' => 'borrador']);
    }

    /**
     * "Verificado" de verdad exige las dos cosas (§4.4, revisión de base de
     * datos, 2026-09-28): el local Y el RUC del negocio dueño — por eso este
     * estado también fuerza `negocio.ruc_verificado`, no solo la columna
     * propia. Ver `Local::estaVerificado()`.
     */
    public function verificado(): static
    {
        return $this->state(fn () => [
            'verificado' => true,
            'verificado_at' => now(),
            'negocio_id' => Negocio::factory()->rucVerificado(),
        ]);
    }

    /** Sin anticipación mínima: útil para agendar "ahora mismo" en un test. */
    public function sinLeadTime(): static
    {
        return $this->state(fn () => ['lead_time_min' => 0]);
    }
}
