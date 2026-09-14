<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Suscripcion (`suscripcion`).
 */
class Suscripcion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'suscripcion';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'profesionales' => 'integer',
            'precio_mensual' => 'decimal:2',
            'vigente_hasta' => 'date',
        ];
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'negocio_id');
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class, 'suscripcion_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
