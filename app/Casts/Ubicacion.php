<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cast para `local.ubicacion` (`geography(Point,4326)`, §4.4).
 *
 * Eloquent no tiene soporte nativo para tipos de PostGIS. Este cast traduce
 * en ambas direcciones:
 *
 * - **Al escribir**: recibe `['lat' => float, 'lng' => float]` y lo convierte
 *   en la expresión SQL `ST_GeogFromText(...)` — PostGIS exige texto, no un
 *   valor de columna común, así que se devuelve como `Expression` cruda.
 * - **Al leer**: Postgres entrega la geography como WKB hexadecimal
 *   (`0101000020E6100000...`), no como texto legible. Se decodifica a mano
 *   porque es un formato fijo y acotado — Point con SRID, little-endian—, no
 *   hace falta una librería para 25 bytes con un layout conocido.
 *
 * Verificado contra Postgres real: `ST_GeogFromText('SRID=4326;POINT(-79.8891 -2.1894)')`
 * vuelve como `0101000020E6100000E9B7AF03E7F853C032E6AE25E48301C0`, que es
 * exactamente 1 (orden) + 4 (tipo+flag SRID) + 4 (SRID) + 8 (X) + 8 (Y) bytes.
 */
final class Ubicacion implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        // Justo después de `create()`/`update()`, el atributo en memoria
        // todavía es la `Expression` que `set()` acaba de construir — Eloquent
        // no vuelve a leer la fila de la base sola. Se decodifica desde ahí
        // mismo en vez de forzar un `fresh()` en cada sitio que escribe esta
        // columna.
        if ($value instanceof Expression) {
            return $this->extraerDeExpresion($value);
        }

        return $this->decodificar($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (! isset($value['lat'], $value['lng'])) {
            throw new RuntimeException("El atributo '{$key}' espera ['lat' => float, 'lng' => float].");
        }

        [$lat, $lng] = [(float) $value['lat'], (float) $value['lng']];

        // Coordenadas en el propio SQL, no como binding: ST_GeogFromText necesita
        // el literal de texto, no puede recibir un parámetro posicional aquí.
        return [$key => new Expression(sprintf("ST_GeogFromText('SRID=4326;POINT(%F %F)')", $lng, $lat))];
    }

    /**
     * Extrae lat/lng del propio SQL que `set()` acaba de construir, sin ir a
     * la base. Es seguro porque el formato lo controlamos nosotros mismos:
     * siempre `POINT(lng lat)` con punto decimal, nunca coma ni notación
     * científica (`%F` en `sprintf`, no `%f` ni `%G`).
     *
     * @return array{lat: float, lng: float}
     */
    private function extraerDeExpresion(Expression $expresion): array
    {
        $sql = (string) $expresion->getValue(DB::connection()->getQueryGrammar());

        if (! preg_match('/POINT\(([-\d.]+) ([-\d.]+)\)/', $sql, $coincidencias)) {
            throw new RuntimeException("No se pudo leer la ubicación recién asignada: '{$sql}'.");
        }

        return ['lng' => (float) $coincidencias[1], 'lat' => (float) $coincidencias[2]];
    }

    /**
     * @return array{lat: float, lng: float}
     */
    private function decodificar(string $hexEwkb): array
    {
        $binario = hex2bin($hexEwkb);

        if ($binario === false || strlen($binario) < 25 || $binario[0] !== "\x01") {
            throw new RuntimeException(
                'Formato de ubicación inesperado: se esperaba WKB little-endian de un Point con SRID.'
            );
        }

        // Cabecera: 1 byte de orden + 4 bytes tipo/flags + 4 bytes SRID = 9 bytes.
        // Luego X (longitud) e Y (latitud), cada una un double little-endian de 8 bytes.
        $x = unpack('e', substr($binario, 9, 8))[1];
        $y = unpack('e', substr($binario, 17, 8))[1];

        return ['lat' => $y, 'lng' => $x];
    }
}
