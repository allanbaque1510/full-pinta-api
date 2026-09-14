<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Negocio (`negocio`).
 */
class Negocio extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'negocio';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'plan_vigente_hasta' => 'date',
        ];
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'propietario_id');
    }

    public function locales(): HasMany
    {
        return $this->hasMany(Local::class, 'negocio_id');
    }

    public function miembros(): HasMany
    {
        return $this->hasMany(NegocioMiembro::class, 'negocio_id');
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class, 'negocio_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** Requiere `plan` cargada (`->with('plan')`) — sin eso, lazy loading roto en desarrollo. */
    public function esPro(): bool
    {
        return $this->plan->codigo === 'pro';
    }
}
