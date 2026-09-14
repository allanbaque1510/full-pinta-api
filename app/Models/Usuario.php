<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario (`usuario`).
 *
 * Un usuario puede ser cliente, profesional, recepción o propietario — o varias
 * cosas a la vez. El rol no es un campo de esta tabla: se resuelve por
 * `negocio_miembro` y `asignacion` vigentes (§3.2).
 *
 * En Ecuador el teléfono es mejor identificador que el email: más gente lo tiene
 * consistente y sirve para WhatsApp. Verificación por OTP.
 */
class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $table = 'usuario';

    protected $guarded = ['id'];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'telefono_verificado' => 'boolean',
            'anonimizado_at' => 'immutable_datetime',
        ];
    }

    /**
     * La especificación llama al campo `password_hash`, no `password`.
     */
    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    /**
     * Cliente sombra: lo creó la recepción para un walk-in y todavía no tiene
     * cuenta. Puede reclamarlo por OTP con el mismo teléfono y heredar su
     * historial (§4.3).
     */
    public function esClienteSombra(): bool
    {
        return $this->password_hash === null;
    }

    public function estaAnonimizado(): bool
    {
        return $this->anonimizado_at !== null;
    }

    // --- Como persona ----------------------------------------------------

    public function perfilCliente(): HasOne
    {
        return $this->hasOne(ClientePerfil::class, 'usuario_id');
    }

    public function mascotas(): HasMany
    {
        return $this->hasMany(Mascota::class, 'usuario_id');
    }

    public function favoritos(): HasMany
    {
        return $this->hasMany(Favorito::class, 'usuario_id');
    }

    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class, 'usuario_id');
    }

    // --- Como cliente ----------------------------------------------------

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'cliente_id');
    }

    public function esperas(): HasMany
    {
        return $this->hasMany(Espera::class, 'cliente_id');
    }

    public function localesVisitados(): HasMany
    {
        return $this->hasMany(ClienteLocal::class, 'usuario_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'reportante_id');
    }

    // --- Como dueño o miembro de un negocio ------------------------------

    public function negociosPropios(): HasMany
    {
        return $this->hasMany(Negocio::class, 'propietario_id');
    }

    public function membresias(): HasMany
    {
        return $this->hasMany(NegocioMiembro::class, 'usuario_id');
    }

    // --- Como profesional ------------------------------------------------

    /**
     * Un usuario puede tener un perfil profesional, pero muchos profesionales
     * no tienen usuario: el local gestiona su agenda (§4.6).
     */
    public function profesional(): HasOne
    {
        return $this->hasOne(Profesional::class, 'usuario_id');
    }

    // --- Notificaciones ---------------------------------------------------

    public function dispositivos(): HasMany
    {
        return $this->hasMany(DeviceToken::class, 'usuario_id');
    }

    public function preferenciasNotificacion(): HasMany
    {
        return $this->hasMany(PreferenciaNotificacion::class, 'usuario_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'usuario_id');
    }
}
