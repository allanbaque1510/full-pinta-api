<?php

namespace App\Models;

use App\Models\Concerns\ClaveCompuesta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ClienteLocal (`cliente_local`).
 *
 * `cita.cliente_nuevo` se calcula contra esta tabla: si no existe la fila, es
 * cliente nuevo para ese local.
 *
 * `nota` es dato del local: el local A nunca ve la nota del local B (§3.3).
 */
class ClienteLocal extends Model
{
    use ClaveCompuesta, HasFactory;

    protected $table = 'cliente_local';

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected $clavePrimaria = ['usuario_id', 'local_id'];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'primera_cita_at' => 'immutable_datetime',
            'ultima_cita_at' => 'immutable_datetime',
            'total_citas' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function profesionalPreferido(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_preferido_id');
    }
}
