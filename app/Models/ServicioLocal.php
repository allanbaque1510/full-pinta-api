<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ServicioLocal (`servicio_local`).
 *
 * El precio y la duración que este local concreto cobra por un servicio del catálogo.
 */
class ServicioLocal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'servicio_local';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'precio_desde' => 'boolean',
            'duracion_min' => 'integer',
            'buffer_min' => 'integer',
            'comisionable' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(CatalogoServicio::class, 'catalogo_servicio_id');
    }

    public function tamanos(): HasMany
    {
        return $this->hasMany(ServicioLocalTamano::class, 'servicio_local_id');
    }

    public function habilidades(): HasMany
    {
        return $this->hasMany(Habilidad::class, 'servicio_local_id');
    }

    public function citaItems(): HasMany
    {
        return $this->hasMany(CitaItem::class, 'servicio_local_id');
    }

    /**
     * Minutos que el slot ocupa de verdad: duración más limpieza posterior.
     */
    public function duracionConBuffer(): int
    {
        return $this->duracion_min + $this->buffer_min;
    }
}
