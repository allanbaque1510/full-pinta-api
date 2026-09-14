<?php

namespace Database\Seeders;

use App\Models\TipoRecurso;
use Illuminate\Database\Seeder;

/**
 * Tipos de recurso (§4.5, §4.6): compartido entre `catalogo_servicio` (qué
 * tipo necesita el servicio) y `recurso` (qué tipo ES la unidad física).
 */
class TipoRecursoSeeder extends Seeder
{
    /**
     * codigo => nombre.
     *
     * @var array<string, string>
     */
    private const TIPOS = [
        'silla' => 'Silla',
        'mesa_unas' => 'Mesa de uñas',
        'lavacabezas' => 'Lavacabezas',
        'tina' => 'Tina',
        'box_privado' => 'Box privado',
        // Solo aplica a servicios del catálogo: un recurso físico siempre es algo concreto.
        'ninguno' => 'Ninguno',
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $codigo => $nombre) {
            TipoRecurso::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
    }
}
