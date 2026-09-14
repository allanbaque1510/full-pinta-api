<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AmenidadCategoria (`amenidad_categoria`): confort, entretenimiento, niños,
 * accesibilidad, pago, política.
 *
 * Tabla de parámetros — referenciada por id desde `amenidad`, en vez de
 * repetir el mismo varchar de categoría en cada fila (§4.4).
 */
class AmenidadCategoria extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'amenidad_categoria';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function amenidades(): HasMany
    {
        return $this->hasMany(Amenidad::class, 'categoria_id');
    }
}
