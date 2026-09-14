<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Amenidad (`amenidad`).
 *
 * "Acepta mascotas en sala" es una amenidad. "Baña perros" es un servicio de la
 * vertical mascotas. Confundirlas lleva clientes con su perro a un local que solo
 * lo deja entrar (§4.4).
 */
class Amenidad extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'amenidad';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Local::class, 'local_amenidad', 'amenidad_id', 'local_id')
            ->withPivot('detalle');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(AmenidadCategoria::class, 'categoria_id');
    }
}
