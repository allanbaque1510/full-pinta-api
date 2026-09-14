<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cita (`cita`).
 *
 * El corazón del sistema. `rango` es columna generada por Postgres y alimenta los
 * constraints de exclusión del §4.11: se lee, nunca se escribe.
 *
 * `propina` va 100% al profesional y NO es base de comisión (§4.7).
 */
class Cita extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cita';

    protected $guarded = ['id', 'rango'];

    protected function casts(): array
    {
        return [
            'inicio' => 'immutable_datetime',
            'fin' => 'immutable_datetime',
            'precio_total' => 'decimal:2',
            'propina' => 'decimal:2',
            'cliente_nuevo' => 'boolean',
            'expira_at' => 'immutable_datetime',
            'confirmada_at' => 'immutable_datetime',
            'cancelada_at' => 'immutable_datetime',
            'completada_at' => 'immutable_datetime',
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

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class, 'recurso_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_id');
    }

    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class, 'mascota_id');
    }

    public function reagendadaDe(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'reagendada_de_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CitaItem::class, 'cita_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(CitaProducto::class, 'cita_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(CitaEvento::class, 'cita_id');
    }

    public function resena(): HasOne
    {
        return $this->hasOne(Resena::class, 'cita_id');
    }

    /** Estados en los que la cita ocupa el slot de verdad (§6). */
    public const ESTADOS_ACTIVOS = ['reservada', 'confirmada', 'en_curso'];

    /**
     * Citas que ocupan slot. El filtro de `expira_at` es indispensable: si el
     * worker de holds se cayó, sin él quedan slots fantasma bloqueados (§5.4).
     */
    public function scopeOcupanSlot(Builder $query): Builder
    {
        return $query->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->where(fn ($q) => $q->where('estado', '<>', 'reservada')
                ->orWhere('expira_at', '>', now()));
    }

    public function scopeHoldsVencidos(Builder $query): Builder
    {
        return $query->where('estado', 'reservada')->where('expira_at', '<=', now());
    }

    public function esTerminal(): bool
    {
        return ! in_array($this->estado, self::ESTADOS_ACTIVOS, true);
    }

    /** Solo una cita completada habilita reseña y cuenta para comisiones (§6). */
    public function admiteResena(): bool
    {
        return $this->estado === 'completada'
            && $this->completada_at?->diffInDays(now()) <= 14;
    }
}
