<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vertical (`vertical`): barbería, estética, uñas, mascotas.
 *
 * Tabla de parámetros — referenciada por id desde `servicio_categoria`,
 * `catalogo_servicio` (vía `categoria_id`) y `solicitud_catalogo`, en vez de
 * repetir el mismo varchar suelto en cada una (§4.5).
 */
class Vertical extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'vertical';

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
        return $this->hasMany(ServicioCategoria::class, 'vertical_id');
    }
}
