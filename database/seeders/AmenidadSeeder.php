<?php

namespace Database\Seeders;

use App\Models\Amenidad;
use App\Models\AmenidadCategoria;
use Illuminate\Database\Seeder;

/**
 * Catálogo de amenidades (§4.4).
 *
 * Distinción crítica: "acepta mascotas en sala" es una amenidad; "baña perros"
 * es un servicio de la vertical mascotas. Confundirlas lleva clientes con su
 * perro a un local que solo lo deja entrar.
 */
class AmenidadSeeder extends Seeder
{
    /**
     * categoria => [codigo => [nombre, icono]]
     *
     * @var array<string, array<string, array{string, string}>>
     */
    private const AMENIDADES = [
        'confort' => [
            'aire_acondicionado' => ['Aire acondicionado', 'snowflake'],
            'sala_de_espera' => ['Sala de espera', 'armchair'],
            'bebidas' => ['Bebidas', 'cup-soda'],
            'cafe' => ['Café', 'coffee'],
            'cerveza' => ['Cerveza', 'beer'],
            'wifi' => ['WiFi', 'wifi'],
            'musica_en_vivo' => ['Música en vivo', 'music'],
        ],
        'entretenimiento' => [
            'tv' => ['TV', 'tv'],
            'consola' => ['Consola de videojuegos', 'gamepad-2'],
            'mesa_de_billar' => ['Mesa de billar', 'circle-dot'],
            'revistas' => ['Revistas', 'book-open'],
        ],
        'ninos' => [
            'area_de_ninos' => ['Área de niños', 'blocks'],
            'guarderia' => ['Guardería', 'baby'],
            'silla_infantil' => ['Silla infantil', 'baby'],
        ],
        'accesibilidad' => [
            'acceso_silla_de_ruedas' => ['Acceso en silla de ruedas', 'accessibility'],
            'bano_accesible' => ['Baño accesible', 'accessibility'],
            'parqueo' => ['Parqueo', 'square-parking'],
            'parqueo_gratis' => ['Parqueo gratis', 'square-parking'],
        ],
        'pago' => [
            'tarjeta' => ['Tarjeta', 'credit-card'],
            'transferencia' => ['Transferencia', 'arrow-left-right'],
            'payphone' => ['Payphone', 'smartphone'],
            'efectivo' => ['Efectivo', 'banknote'],
        ],
        'politica' => [
            'atiende_mujeres' => ['Atiende mujeres', 'users'],
            'atiende_ninos' => ['Atiende niños', 'baby'],
            'acepta_mascotas_en_sala' => ['Acepta mascotas en sala', 'dog'],
            'solo_con_cita' => ['Solo con cita', 'calendar-check'],
            'atiende_sin_cita' => ['Atiende sin cita', 'door-open'],
        ],
    ];

    public function run(): void
    {
        foreach (self::AMENIDADES as $categoria => $amenidades) {
            $categoriaId = AmenidadCategoria::where('codigo', $categoria)->value('id');

            foreach ($amenidades as $codigo => [$nombre, $icono]) {
                Amenidad::updateOrCreate(
                    ['codigo' => $codigo],
                    ['categoria_id' => $categoriaId, 'nombre' => $nombre, 'icono' => $icono, 'activo' => true],
                );
            }
        }
    }
}
