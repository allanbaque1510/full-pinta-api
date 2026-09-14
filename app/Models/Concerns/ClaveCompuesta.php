<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Soporte de clave primaria compuesta.
 *
 * Eloquent no las soporta — la especificación ya lo advierte en §4.13, y es la
 * razón por la que `cita` se deja sin particionar. Pero varias tablas del
 * esquema sí las llevan legítimamente, porque son relaciones o proyecciones:
 * `cliente_local`, `servicio_local_tamano`, `disponibilidad_dia` y
 * `preferencia_notificacion`.
 *
 * El modelo declara `$clavePrimaria` con las columnas, y este trait se encarga
 * de que `save()`, `delete()` y `refresh()` apunten a la fila correcta en vez
 * de a una columna `id` que no existe.
 */
trait ClaveCompuesta
{
    // `$incrementing` y `$keyType` los declara cada modelo, no este trait: PHP
    // considera incompatible que un trait redefina una propiedad que ya existe
    // en la jerarquía de la clase, y `Model` declara ambas.

    /**
     * Valores de la clave, en el orden declarado.
     *
     * @return array<string,mixed>
     */
    public function clave(): array
    {
        $clave = [];

        foreach ($this->clavePrimaria as $columna) {
            $clave[$columna] = $this->getAttribute($columna);
        }

        return $clave;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $this->aplicarClave($query);
    }

    protected function setKeysForSelectQuery($query): Builder
    {
        return $this->aplicarClave($query);
    }

    private function aplicarClave($query): Builder
    {
        foreach ($this->clavePrimaria as $columna) {
            // `getOriginal` para que renombrar parte de la clave siga apuntando
            // a la fila original al guardar.
            $query->where($columna, $this->getOriginal($columna, $this->getAttribute($columna)));
        }

        return $query;
    }

    public function getKeyName(): array
    {
        return $this->clavePrimaria;
    }

    public function getKey(): array
    {
        return $this->clave();
    }
}
