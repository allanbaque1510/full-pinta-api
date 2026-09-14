<?php

namespace App\Models;

use App\Models\Concerns\ClaveCompuesta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DisponibilidadDia (`disponibilidad_dia`).
 *
 * Read model. El filtro "disponible hoy" no puede correr el motor de slots por
 * cada local del resultado: se cae. La búsqueda consulta esto, que un job
 * reconstruye por evento (§12.5).
 */
class DisponibilidadDia extends Model
{
    use ClaveCompuesta, HasFactory;

    protected $table = 'disponibilidad_dia';

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected $clavePrimaria = ['local_id', 'fecha'];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'slots_libres' => 'integer',
            'recalculado_at' => 'immutable_datetime',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }
}
