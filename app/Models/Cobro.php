<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cobro (`cobro`).
 */
class Cobro extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cobro';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'intentos' => 'integer',
            'pagado_at' => 'immutable_datetime',
        ];
    }

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(Suscripcion::class, 'suscripcion_id');
    }
}
