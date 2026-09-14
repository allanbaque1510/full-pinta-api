<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto (`producto`).
 *
 * Necesarios para que la liquidación de comisiones sea correcta: llevan
 * porcentaje distinto (o cero) al de un servicio (§4.5).
 */
class Producto extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'producto';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'comision_pct' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function citaProductos(): HasMany
    {
        return $this->hasMany(CitaProducto::class, 'producto_id');
    }
}
