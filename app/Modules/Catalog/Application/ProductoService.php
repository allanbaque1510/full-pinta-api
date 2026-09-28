<?php

namespace App\Modules\Catalog\Application;

use App\Models\Local;
use App\Models\Producto;
use App\Modules\Catalog\Events\ProductoModificado;
use App\Support\ImagenService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `Producto` (§4.5): necesarios para que la
 * liquidación de comisiones sea correcta — llevan un porcentaje distinto (o
 * cero) al de un servicio.
 */
final readonly class ProductoService
{
    public function __construct(private ImagenService $imagenes) {}

    public function listar(Local $local): Collection
    {
        return $local->productos()->with('foto')->get();
    }

    public function crear(Local $local, array $datos): Producto
    {
        $fotoUrl = $datos['foto_url'] ?? null;
        unset($datos['foto_url']);

        // `comision_pct` es opcional en el request pero tiene DEFAULT 0 en
        // Postgres — ese default vive en la base, no en PHP: si se omite,
        // el modelo recién creado queda con `null` en memoria en vez de 0
        // hasta que alguien lo recargue. `activo` nunca lo manda el cliente.
        $producto = $local->productos()->create([
            'comision_pct' => 0,
            ...$datos,
            'activo' => true,
        ]);

        if ($fotoUrl !== null) {
            $this->imagenes->establecerFotoPerfil($producto, 'producto', $fotoUrl, 'foto_id');
        }

        ProductoModificado::dispatch($local->id);

        return $producto->load('foto');
    }

    public function actualizar(Producto $producto, array $datos): Producto
    {
        if (array_key_exists('foto_url', $datos)) {
            $this->imagenes->establecerFotoPerfil($producto, 'producto', $datos['foto_url'], 'foto_id');
            unset($datos['foto_url']);
        }

        $producto->update($datos);

        ProductoModificado::dispatch($producto->local_id);

        return $producto->load('foto');
    }

    /** No se borra: se desactiva. `cita_producto` ya vendido queda intacto. */
    public function desactivar(Producto $producto): void
    {
        $producto->update(['activo' => false]);

        ProductoModificado::dispatch($producto->local_id);
    }
}
