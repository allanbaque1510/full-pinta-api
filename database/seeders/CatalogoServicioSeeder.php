<?php

namespace Database\Seeders;

use App\Models\CatalogoServicio;
use App\Models\ServicioCategoria;
use App\Models\TipoRecurso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catálogo maestro de servicios (§4.5).
 *
 * Lo define la plataforma, no los locales. Si cada local escribiera sus
 * servicios en texto libre se terminaría con "corte caballero", "corte de
 * cabello", "fade" y "CORTE" como cosas distintas, y la búsqueda y el filtro de
 * precios morirían.
 *
 * `duracion_base_min` es solo una sugerencia: cada local fija la suya en
 * `servicio_local`.
 */
class CatalogoServicioSeeder extends Seeder
{
    /**
     * vertical => [[nombre, categoria, duración base en minutos, tipo de recurso]]
     *
     * @var array<string, array<int, array{string, string, int, string}>>
     */
    private const SERVICIOS = [
        'barberia' => [
            ['Corte clásico', 'corte', 30, 'silla'],
            ['Corte fade', 'corte', 40, 'silla'],
            ['Corte + barba', 'corte', 55, 'silla'],
            ['Perfilado de barba', 'barba', 20, 'silla'],
            ['Tinte de barba', 'color', 30, 'silla'],
            ['Cejas', 'cejas', 10, 'silla'],
            ['Mascarilla negra', 'tratamiento', 20, 'silla'],
        ],
        'estetica' => [
            ['Corte de dama', 'corte', 45, 'silla'],
            ['Cepillado', 'peinado', 40, 'silla'],
            ['Secado', 'peinado', 30, 'silla'],
            ['Peinado de evento', 'peinado', 60, 'silla'],
            ['Tinte', 'color', 90, 'lavacabezas'],
            ['Mechas', 'color', 150, 'lavacabezas'],
            ['Keratina', 'tratamiento', 120, 'lavacabezas'],
            ['Depilación de cejas', 'depilacion', 15, 'silla'],
        ],
        'unas' => [
            ['Manicura', 'manicura', 40, 'mesa_unas'],
            ['Pedicura', 'pedicura', 50, 'mesa_unas'],
            ['Esmaltado semipermanente', 'esmaltado', 60, 'mesa_unas'],
            ['Uñas acrílicas', 'extension', 120, 'mesa_unas'],
            ['Retiro', 'extension', 30, 'mesa_unas'],
        ],
        // Modelada pero no activada en la v1 (§15.2). El precio y la duración
        // reales dependen del tamaño del animal, vía `servicio_local_tamano`.
        'mascotas' => [
            ['Baño', 'bano', 45, 'tina'],
            ['Baño + corte', 'bano', 90, 'tina'],
            ['Deslanado', 'corte', 60, 'tina'],
            ['Corte de uñas', 'higiene', 15, 'tina'],
            ['Limpieza de oídos', 'higiene', 15, 'tina'],
        ],
    ];

    public function run(): void
    {
        $tiposRecurso = TipoRecurso::all()->keyBy('codigo');

        foreach (self::SERVICIOS as $vertical => $servicios) {
            $categorias = ServicioCategoria::query()
                ->whereHas('vertical', fn ($q) => $q->where('codigo', $vertical))
                ->get()
                ->keyBy('codigo');

            foreach ($servicios as [$nombre, $categoria, $duracion, $recurso]) {
                // El slug lleva la vertical por delante para que no colisione
                // cuando dos verticales tengan un servicio con el mismo nombre.
                $slug = Str::slug("{$vertical} {$nombre}");

                CatalogoServicio::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'categoria_id' => $categorias[$categoria]->id,
                        'nombre' => $nombre,
                        'duracion_base_min' => $duracion,
                        'tipo_recurso_id' => $tiposRecurso[$recurso]->id,
                        'activo' => true,
                    ],
                );
            }
        }
    }
}
