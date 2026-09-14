<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planes de la plataforma (§4.4, §4.10): compartido entre `negocio` (plan
 * actual) y `suscripcion` (Billing).
 */
class PlanSeeder extends Seeder
{
    /**
     * codigo => nombre.
     *
     * @var array<string, string>
     */
    private const PLANES = [
        'free' => 'Free',
        'pro' => 'Pro',
    ];

    public function run(): void
    {
        foreach (self::PLANES as $codigo => $nombre) {
            Plan::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
    }
}
