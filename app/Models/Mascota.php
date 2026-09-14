<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mascota (`mascota`).
 */
class Mascota extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mascota';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'peso_kg' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function tamano(): BelongsTo
    {
        return $this->belongsTo(TamanoMascota::class, 'tamano_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'mascota_id');
    }
}
