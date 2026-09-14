<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte (`reporte`).
 *
 * `tipo` + `objeto_id` es polimórfico a propósito: se reporta cualquier cosa.
 */
class Reporte extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'reporte';

    protected $guarded = ['id'];

    public function reportante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'reportante_id');
    }
}
