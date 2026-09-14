<?php

namespace App\Modules\Identity\Application;

use App\Models\Otp;
use App\Models\Usuario;
use App\Modules\Identity\Application\Exceptions\CodigoOtpIncorrecto;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Verifica el código y resuelve el usuario: lo crea, lo reclama si era cliente
 * sombra, o simplemente lo autentica si ya existía (§4.3).
 *
 * `password_hash` no se usa para ningún inicio de sesión por contraseña —esta
 * plataforma solo autentica por OTP—; su único papel es el que describe la
 * especificación: NULL marca un registro sin reclamar. Al reclamar o registrar,
 * se le asigna un hash aleatorio e inutilizable, solo para salir del estado
 * `NULL`. Ver skill `modulo` si esto se toca.
 *
 * **Máximo `fullpinta.max_dispositivos_activos` sesiones a la vez** (2 por
 * defecto): al iniciar sesión por encima del límite, se cierra la más
 * antigua — nunca se rechaza el login nuevo. Es una decisión de producto, no
 * una limitación técnica de Sanctum (que soporta cualquier cantidad de
 * tokens por usuario, ver `TokenSanctumTest`): propietario y recepción suelen
 * necesitar su teléfono personal Y la tablet del mostrador abiertos a la vez,
 * así que un límite de 1 los echaría constantemente el uno al otro.
 */
final readonly class VerificarOtp
{
    public function __construct(
        private OtorgarConsentimiento $otorgarConsentimiento,
        private LimitarSesionesActivas $limitarSesiones,
    ) {}

    /**
     * @return array{0: Usuario, 1: NewAccessToken}
     */
    public function __invoke(string $telefono, string $codigo, ?string $nombre = null): array
    {
        $otp = Otp::where('telefono', $telefono)
            ->whereNull('verificado_at')
            ->where('expira_at', '>', now())
            ->latest('created_at')
            ->first();

        if ($otp === null) {
            throw_validacion('El código no es válido o ya expiró. Solicita uno nuevo.', 'codigo');
        }

        $maximo = (int) config('fullpinta.otp.max_intentos_verificacion');

        if ($otp->intentos >= $maximo) {
            throw_validacion('El código no es válido o ya expiró. Solicita uno nuevo.', 'codigo');
        }

        if (! Hash::check($codigo, $otp->codigo_hash)) {
            $otp->increment('intentos');

            throw new CodigoOtpIncorrecto($maximo - $otp->intentos);
        }

        $otp->update(['verificado_at' => now()]);

        $usuario = Usuario::where('telefono', $telefono)->first();

        if ($usuario === null) {
            $usuario = $this->registrar($telefono, $nombre);
        } elseif ($usuario->esClienteSombra()) {
            $usuario = $this->reclamar($usuario, $nombre);
        } elseif (! $usuario->telefono_verificado) {
            $usuario->update(['telefono_verificado' => true]);
        }

        ($this->limitarSesiones)($usuario);

        $token = $usuario->createToken('otp');

        return [$usuario->fresh(), $token];
    }

    private function registrar(string $telefono, ?string $nombre): Usuario
    {
        if (blank($nombre)) {
            throw_validacion('Este teléfono es nuevo: indica un nombre para completar el registro.', 'nombre');
        }

        $usuario = Usuario::create([
            'telefono' => $telefono,
            'telefono_verificado' => true,
            'nombre' => $nombre,
            'password_hash' => $this->hashInutilizable(),
        ]);

        $this->registrarConsentimientoDeOperacion($usuario);

        return $usuario;
    }

    /**
     * La recepción ya creó este usuario con solo nombre y teléfono para un
     * walk-in. Reclamarlo NO crea una fila nueva: sigue siendo el mismo
     * `usuario_id`, así que hereda su historial de citas sin más trabajo
     * (§4.3).
     */
    private function reclamar(Usuario $usuario, ?string $nombre): Usuario
    {
        $usuario->update([
            'telefono_verificado' => true,
            'password_hash' => $this->hashInutilizable(),
            'nombre' => filled($nombre) ? $nombre : $usuario->nombre,
        ]);

        $this->registrarConsentimientoDeOperacion($usuario);

        return $usuario;
    }

    private function hashInutilizable(): string
    {
        return Hash::make(Str::random(40));
    }

    /**
     * Operar la cita no es lo mismo que recibir promociones (§4.3, §13.1): solo
     * se otorga el consentimiento operativo aquí. El de marketing se pide
     * aparte, en su propio momento, con su propia pantalla.
     */
    private function registrarConsentimientoDeOperacion(Usuario $usuario): void
    {
        ($this->otorgarConsentimiento)($usuario, 'operacion_servicio', origen: 'app');
    }
}
