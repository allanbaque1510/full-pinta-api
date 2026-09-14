<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan (`plan`): free, pro.
 *
 * Tabla de parámetros — referenciada por id desde `negocio` (plan actual) y
 * desde `suscripcion` (Billing), en vez del mismo varchar+CHECK repetido en
 * las dos tablas.
 */
class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'plan';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function negocios(): HasMany
    {
        return $this->hasMany(Negocio::class, 'plan_id');
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class, 'plan_id');
    }
}
