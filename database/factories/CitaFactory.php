<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cita>
 *
 * Por defecto una cita confirmada de una hora. `rango` lo calcula Postgres a
 * partir de `inicio` y `fin`: no se escribe desde aquí.
 */
class CitaFactory extends Factory
{
    protected $model = Cita::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'profesional_id' => Profesional::factory(),
            'recurso_id' => null,
            'cliente_id' => Usuario::factory(),
            'inicio' => $inicio = now()->addDay()->setTime(10, 0),
            'fin' => $inicio->copy()->addHour(),
            'estado' => 'confirmada',
            'canal' => 'app',
            'precio_total' => fake()->randomFloat(2, 5, 60),
            'propina' => 0,
            'cliente_nuevo' => false,
            'para_tipo' => 'titular',
            'codigo' => strtoupper(Str::random(8)),
            'confirmada_at' => now(),
        ];
    }

    /** Hold sin confirmar: expira a los 10 minutos (§5.4). */
    public function reservada(): static
    {
        return $this->state(fn () => [
            'estado' => 'reservada',
            'confirmada_at' => null,
            'expira_at' => now()->addMinutes(10),
        ]);
    }

    /** Hold que el job de expiración todavía no limpió. */
    public function holdVencido(): static
    {
        return $this->state(fn () => [
            'estado' => 'reservada',
            'confirmada_at' => null,
            'expira_at' => now()->subMinute(),
        ]);
    }

    public function completada(): static
    {
        return $this->state(fn () => [
            'estado' => 'completada',
            'completada_at' => now(),
        ]);
    }

    public function cancelada(string $por = 'cliente'): static
    {
        return $this->state(fn () => [
            'estado' => "cancelada_{$por}",
            'cancelada_at' => now(),
        ]);
    }

    /** Walk-in creado en el mostrador: ocupa slot igual (§4.7). */
    public function walkIn(): static
    {
        return $this->state(fn () => ['canal' => 'local']);
    }

    public function entre(\DateTimeInterface $inicio, \DateTimeInterface $fin): static
    {
        return $this->state(fn () => ['inicio' => $inicio, 'fin' => $fin]);
    }
}
