<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consentimiento (`consentimiento`).
 *
 * Se registra POR FINALIDAD, con versión y timestamp, y debe poder probarse
 * (§13.1). Operar la cita no es lo mismo que recibir promociones.
 */
class Consentimiento extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'consentimiento';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'otorgado' => 'boolean',
            'otorgado_at' => 'immutable_datetime',
            'revocado_at' => 'immutable_datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function estaVigente(): bool
    {
        return $this->otorgado && $this->revocado_at === null;
    }
}
