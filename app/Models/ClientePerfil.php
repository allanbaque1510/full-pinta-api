<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ClientePerfil (`cliente_perfil`).
 *
 * Confiabilidad del cliente. Se registra pero NO se muestra como puntaje
 * público al cliente: es hostil y lo espanta. Uso interno — al 3er no-show se
 * activa `requiere_confirmacion` (§4.3).
 */
class ClientePerfil extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cliente_perfil';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'no_shows' => 'integer',
            'cancelaciones_tardias' => 'integer',
            'requiere_confirmacion' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
