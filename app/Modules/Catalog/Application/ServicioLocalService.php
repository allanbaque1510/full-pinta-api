<?php

namespace App\Modules\Catalog\Application;

use App\Models\CatalogoServicio;
use App\Models\Local;
use App\Models\Recurso;
use App\Models\ServicioLocal;
use App\Models\TamanoMascota;
use App\Modules\Catalog\Events\ServicioLocalModificado;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `ServicioLocal` (§4.5): el precio y la
 * duración que un local concreto cobra por un servicio del catálogo maestro.
 */
final readonly class ServicioLocalService
{
    public function listar(Local $local): Collection
    {
        return $local->servicios()->with('catalogoServicio')->get();
    }

    public function crear(Local $local, array $datos): ServicioLocal
    {
        if ($local->servicios()->where('catalogo_servicio_id', $datos['catalogo_servicio_id'])->exists()) {
            throw_validacion('Este local ya tiene ese servicio dado de alta.', 'catalogo_servicio_id');
        }

        // Los tres primeros son opcionales en el request pero tienen DEFAULT
        // en Postgres — ese default vive en la base, no en PHP. Si Eloquent
        // omite la columna al insertar, el modelo recién creado queda con
        // esos campos en `null` en memoria hasta que alguien lo recargue.
        // `activo` nunca lo manda el cliente: siempre nace activo.
        $servicio = $local->servicios()->create([
            'precio_desde' => false,
            'buffer_min' => 0,
            'comisionable' => true,
            ...$datos,
            'activo' => true,
        ]);

        $servicio->load('catalogoServicio.tipoRecurso');
        $this->asegurarRecursoPorDefecto($local, $servicio->catalogoServicio);

        ServicioLocalModificado::dispatch($local->id);

        return $servicio;
    }

    /**
     * Un local que activa su primer servicio de un tipo de recurso (p. ej. el
     * primer servicio de uñas) no debería quedarse sin poder agendar por no
     * haber dado de alta manualmente una mesa — se le crea una por defecto
     * (§4.6). 'ninguno' es el tipo centinela que marca "no hace falta
     * recurso" (barbería, tras esta misma revisión): esos servicios no crean
     * nada.
     */
    private function asegurarRecursoPorDefecto(Local $local, CatalogoServicio $catalogoServicio): void
    {
        if ($catalogoServicio->tipoRecurso->codigo === 'ninguno') {
            return;
        }

        $tieneRecursoActivo = Recurso::where('local_id', $local->id)
            ->where('tipo_recurso_id', $catalogoServicio->tipo_recurso_id)
            ->where('activo', true)
            ->exists();

        if (! $tieneRecursoActivo) {
            Recurso::create([
                'local_id' => $local->id,
                'tipo_recurso_id' => $catalogoServicio->tipo_recurso_id,
                'nombre' => $catalogoServicio->tipoRecurso->nombre.' 1',
                'activo' => true,
            ]);
        }
    }

    public function actualizar(ServicioLocal $servicio, array $datos): ServicioLocal
    {
        $servicio->update($datos);

        ServicioLocalModificado::dispatch($servicio->local_id);

        return $servicio->load('catalogoServicio');
    }

    /**
     * No se borra: se desactiva (§4.2, campo `activo`). Las citas ya
     * agendadas contra este servicio (`cita_item`, con precio congelado) no
     * deben verse afectadas.
     */
    public function desactivar(ServicioLocal $servicio): void
    {
        $servicio->update(['activo' => false]);

        ServicioLocalModificado::dispatch($servicio->local_id);
    }

    /**
     * Reemplaza el conjunto completo de precios por tamaño — obligatorio para
     * grooming: bañar un yorkshire no cuesta lo mismo que un golden (§4.5).
     *
     * @param  array<int,array{tamano: string, precio: float, duracion_min: int}>  $tamanos  `tamano` es el código de `tamano_mascota`, no su id.
     */
    public function sincronizarTamanos(ServicioLocal $servicio, array $tamanos): Collection
    {
        // Sin esto se le podría asignar precio por tamaño de mascota a un
        // corte de pelo de barbería: no hay CHECK de Postgres posible, la
        // cadena servicio_local -> catalogo_servicio -> servicio_categoria ->
        // rubro cruza tres FKs (revisión de base de datos, 2026-09-28).
        if ($tamanos !== []) {
            $servicio->loadMissing('catalogoServicio.categoria.rubro');

            if ($servicio->catalogoServicio->categoria->rubro->codigo !== 'mascotas') {
                throw_validacion('Este servicio no pertenece al rubro mascotas; no admite precio por tamaño.', 'tamanos');
            }
        }

        $servicio->tamanos()->delete();

        foreach ($tamanos as $tamano) {
            $servicio->tamanos()->create([
                'tamano_id' => TamanoMascota::where('codigo', $tamano['tamano'])->value('id'),
                'precio' => $tamano['precio'],
                'duracion_min' => $tamano['duracion_min'],
            ]);
        }

        return $servicio->tamanos()->with('tamano')->get();
    }
}
