<?php

namespace App\Modules\Catalog\Application;

use App\Models\Local;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `Producto` (§4.5): necesarios para que la
 * liquidación de comisiones sea correcta — llevan un porcentaje distinto (o
 * cero) al de un servicio.
 */
final readonly class ProductoService
{
    public function listar(Local $local): Collection
    {
        return $local->productos;
    }

    public function crear(Local $local, array $datos): Producto
    {
        // `comision_pct` es opcional en el request pero tiene DEFAULT 0 en
        // Postgres — ese default vive en la base, no en PHP: si se omite,
        // el modelo recién creado queda con `null` en memoria en vez de 0
        // hasta que alguien lo recargue. `activo` nunca lo manda el cliente.
        return $local->productos()->create([
            'comision_pct' => 0,
            ...$datos,
            'activo' => true,
        ]);
    }

    public function actualizar(Producto $producto, array $datos): Producto
    {
        $producto->update($datos);

        return $producto;
    }

    /** No se borra: se desactiva. `cita_producto` ya vendido queda intacto. */
    public function desactivar(Producto $producto): void
    {
        $producto->update(['activo' => false]);
    }
}
