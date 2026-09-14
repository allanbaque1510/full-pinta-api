<?php

namespace Database\Seeders;

use App\Models\NotificacionCategoria;
use Illuminate\Database\Seeder;

/**
 * Categorías de notificación (§4.9): compartido entre
 * `preferencia_notificacion` y `notificacion`.
 */
class NotificacionCategoriaSeeder extends Seeder
{
    /**
     * codigo => nombre.
     *
     * @var array<string, string>
     */
    private const CATEGORIAS = [
        'citas' => 'Citas',
        'agenda' => 'Agenda',
        'social' => 'Social',
        'promos' => 'Promociones',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIAS as $codigo => $nombre) {
            NotificacionCategoria::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
    }
}
