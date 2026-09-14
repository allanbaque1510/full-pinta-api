<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\Producto;
use App\Modules\Catalog\Application\ProductoService;
use App\Modules\Catalog\Http\Requests\ActualizarProductoRequest;
use App\Modules\Catalog\Http\Requests\CrearProductoRequest;
use App\Modules\Catalog\Http\Resources\ProductoResource;
use Illuminate\Http\JsonResponse;

class ProductoController extends Controller
{
    public function index(Local $local, ProductoService $productos): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $productos) {
            $this->authorize('ver', $local);

            return ProductoResource::collection($productos->listar($local));
        });
    }

    public function store(CrearProductoRequest $request, Local $local, ProductoService $productos): JsonResponse
    {
        return $this->ejecutar(fn () => ProductoResource::make($productos->crear($local, $request->validated())), 201);
    }

    public function update(ActualizarProductoRequest $request, Producto $producto, ProductoService $productos): JsonResponse
    {
        return $this->ejecutar(fn () => ProductoResource::make($productos->actualizar($producto, $request->validated())));
    }

    public function destroy(Producto $producto, ProductoService $productos): JsonResponse
    {
        return $this->ejecutar(function () use ($producto, $productos) {
            $this->authorize('gestionarCatalogo', $producto->local);

            $productos->desactivar($producto);

            return response()->json(status: 204);
        });
    }
}
