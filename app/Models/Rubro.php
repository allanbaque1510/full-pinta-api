<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rubro (`rubro`): barbería, estética, uñas, mascotas.
 *
 * Tabla de parámetros — referenciada por id desde `servicio_categoria`,
 * `catalogo_servicio` (vía `categoria_id`) y `solicitud_catalogo`, en vez de
 * repetir el mismo varchar suelto en cada una (§4.5). Se llamó `Vertical`
 * hasta el 2026-09-28 — renombrado por preferencia de nomenclatura.
 */
class Rubro extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rubro';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(ServicioCategoria::class, 'rubro_id');
    }
}
