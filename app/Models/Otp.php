<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Otp (`otp`).
 *
 * Código de un solo uso para verificar un teléfono. No forma parte de las 43
 * tablas del §4 — la especificación describe la verificación por OTP pero no
 * su esquema — así que esta tabla es una adición del código, no del documento.
 *
 * El código nunca se guarda en claro: `codigo_hash` se compara con
 * `Hash::check()`, igual que una contraseña.
 */
class Otp extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'otp';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'intentos' => 'integer',
            'expira_at' => 'immutable_datetime',
            'verificado_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function estaVigente(): bool
    {
        return $this->verificado_at === null && $this->expira_at->isFuture();
    }
}
