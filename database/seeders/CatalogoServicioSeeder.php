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
     * rubro => [[nombre, categoria, duración base en minutos, tipo de recurso]]
     *
     * @var array<string, array<int, array{string, string, int, string}>>
     */
    private const SERVICIOS = [
        // 'ninguno' (revisión de base de datos, 2026-09-28): una barbería no
        // depende de una silla física para agendar — el profesional es el
        // recurso escaso, no el mueble. Modelarlo con 'silla' obligaba a un
        // `recurso` por barbero solo para satisfacer el constraint, sin que
        // nada lo necesitara de verdad.
        'barberia' => [
            ['Corte clásico', 'corte', 30, 'ninguno'],
            ['Corte fade', 'corte', 40, 'ninguno'],
            ['Corte + barba', 'corte', 55, 'ninguno'],
            ['Perfilado de barba', 'barba', 20, 'ninguno'],
            ['Tinte de barba', 'color', 30, 'ninguno'],
            ['Cejas', 'cejas', 10, 'ninguno'],
            ['Mascarilla negra', 'tratamiento', 20, 'ninguno'],
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

        foreach (self::SERVICIOS as $rubro => $servicios) {
            $categorias = ServicioCategoria::query()
                ->whereHas('rubro', fn ($q) => $q->where('codigo', $rubro))
                ->get()
                ->keyBy('codigo');

            foreach ($servicios as [$nombre, $categoria, $duracion, $recurso]) {
                // El slug lleva el rubro por delante para que no colisione
                // cuando dos rubros tengan un servicio con el mismo nombre.
                $slug = Str::slug("{$rubro} {$nombre}");

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
