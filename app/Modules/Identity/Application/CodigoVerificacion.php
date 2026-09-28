<?php

namespace App\Modules\Identity\Application;

use App\Models\Otp;
use App\Modules\Identity\Application\Contracts\EnviadorCodigoEmail;
use App\Modules\Identity\Application\Contracts\EnviadorOtp;
use App\Modules\Identity\Application\Exceptions\CodigoOtpIncorrecto;
use App\Modules\Identity\Application\Exceptions\DemasiadasSolicitudesOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Generación y verificación de códigos de un solo uso contra `otp`, para
 * cualquier flujo que NO sea login/registro por teléfono — ese ya tiene su
 * propia clase (`SolicitarOtp`/`VerificarOtp`), con efectos de sesión propios
 * (crear cuenta, reclamar cliente sombra) que no le corresponden a esta.
 *
 * Hoy la usan la verificación de propiedad del email y la recuperación de
 * contraseña — ambas necesitan exactamente lo mismo: "probar que esta persona
 * controla este teléfono o este correo", nada más. Mismos límites que el OTP
 * de login (`fullpinta.otp.*`): es el mismo tipo de código, por el mismo canal.
 */
final readonly class CodigoVerificacion
{
    public function __construct(
        private EnviadorOtp $enviadorTelefono,
        private EnviadorCodigoEmail $enviadorEmail,
    ) {}

    public function enviarATelefono(string $telefono): void
    {
        $this->enviadorTelefono->enviar($telefono, $this->generar('telefono', $telefono));
    }

    public function enviarAEmail(string $email): void
    {
        $this->enviadorEmail->enviar($email, $this->generar('email', $email));
    }

    public function verificarTelefono(string $telefono, string $codigo): Otp
    {
        return $this->verificar('telefono', $telefono, $codigo);
    }

    public function verificarEmail(string $email, string $codigo): Otp
    {
        return $this->verificar('email', $email, $codigo);
    }

    private function generar(string $columna, string $destino): string
    {
        $clave = "codigo_verificacion:{$columna}:{$destino}";
        $maximo = (int) config('fullpinta.otp.max_solicitudes_por_ventana');
        $ventanaSegundos = (int) config('fullpinta.otp.ventana_minutos') * 60;

        if (RateLimiter::tooManyAttempts($clave, $maximo)) {
            throw new DemasiadasSolicitudesOtp(RateLimiter::availableIn($clave));
        }

        RateLimiter::hit($clave, $ventanaSegundos);

        // Solo el código más reciente de este destino es válido.
        Otp::where($columna, $destino)
            ->whereNull('verificado_at')
            ->update(['expira_at' => now()]);

        $codigo = $this->generarCodigo();

        Otp::create([
            $columna => $destino,
            'codigo_hash' => bcrypt($codigo),
            'expira_at' => now()->addMinutes((int) config('fullpinta.otp.expira_minutos')),
        ]);

        return $codigo;
    }

    private function verificar(string $columna, string $destino, string $codigo): Otp
    {
        $otp = Otp::where($columna, $destino)
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

        return $otp;
    }

    private function generarCodigo(): string
    {
        $digitos = (int) config('fullpinta.otp.digitos');
        $min = (int) str_pad('1', $digitos, '0');
        $max = (int) str_pad('', $digitos, '9');

        return (string) random_int($min, $max);
    }
}
