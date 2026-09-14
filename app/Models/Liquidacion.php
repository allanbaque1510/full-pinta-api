<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liquidacion (`liquidacion`).
 *
 * `total_propinas` se suma al pago del profesional pero NO entra en la base de
 * comisión (§4.10).
 */
class Liquidacion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'liquidacion';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'total_servicios' => 'decimal:2',
            'total_productos' => 'decimal:2',
            'total_propinas' => 'decimal:2',
            'comision_servicios' => 'decimal:2',
            'comision_productos' => 'decimal:2',
            'total_a_pagar' => 'decimal:2',
            'cerrada_at' => 'immutable_datetime',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }
}
