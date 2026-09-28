<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Imagen (`imagen`): galería polimórfica compartida por `local`, `profesional`,
 * `mascota`, `usuario`... (§4.4). Reemplaza `local_foto`/`profesional_foto`
 * (una tabla por dueño, mismo shape repetido) y los `foto_url` sueltos de
 * `usuario`/`profesional` (revisión de base de datos, 2026-09-28).
 *
 * `objeto_type` viaja por `Relation::morphMap()` (ver `AppServiceProvider`),
 * nunca el FQCN — convención pura de Laravel (`uuidMorphs`).
 */
class Imagen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'imagen';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function objeto(): MorphTo
    {
        return $this->morphTo();
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoImagen::class, 'tipo_id');
    }
}
