<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CitaItem (`cita_item`).
 *
 * Precio y comisión CONGELADOS al agendar. Si el local sube precios mañana, la
 * cita de ayer mantiene el pactado; si el dueño cambia el porcentaje al barbero,
 * las citas ya atendidas se liquidan con el vigente entonces. Sin esto, cambiar
 * una comisión reescribe el pasado (§4.7).
 */
class CitaItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cita_item';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'duracion_min' => 'integer',
            'comisionable' => 'boolean',
            'comision_pct' => 'decimal:2',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function servicioLocal(): BelongsTo
    {
        return $this->belongsTo(ServicioLocal::class, 'servicio_local_id');
    }
}
