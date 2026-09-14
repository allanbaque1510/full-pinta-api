<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ServicioCategoria (`servicio_categoria`).
 *
 * `codigo` es único solo junto a `vertical_id`, nunca por sí solo: el mismo
 * código existe en verticales distintas — `corte` es categoría de barbería y
 * también de estética, y no son la misma cosa (§4.5).
 */
class ServicioCategoria extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'servicio_categoria';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function vertical(): BelongsTo
    {
        return $this->belongsTo(Vertical::class, 'vertical_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(CatalogoServicio::class, 'categoria_id');
    }
}
