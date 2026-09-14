<?php

namespace App\Modules\Identity\Application;

use App\Models\Otp;
use App\Modules\Identity\Application\Contracts\EnviadorOtp;
use App\Modules\Identity\Application\Exceptions\DemasiadasSolicitudesOtp;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Genera y envía un código de un solo uso a un teléfono.
 *
 * Sirve tanto para registro como para inicio de sesión: el endpoint es el
 * mismo, y quién resulte ser el teléfono (nuevo, cliente sombra, o ya
 * registrado) se decide recién en `VerificarOtp`.
 */
final readonly class SolicitarOtp
{
    public function __construct(private EnviadorOtp $enviador) {}

    public function __invoke(string $telefono): void
    {
        $clave = "otp:{$telefono}";
        $maximo = (int) config('fullpinta.otp.max_solicitudes_por_ventana');
        $ventanaSegundos = (int) config('fullpinta.otp.ventana_minutos') * 60;

        if (RateLimiter::tooManyAttempts($clave, $maximo)) {
            throw new DemasiadasSolicitudesOtp(RateLimiter::availableIn($clave));
        }

        RateLimiter::hit($clave, $ventanaSegundos);

        // Solo el código más reciente es válido: cualquier otro pendiente de
        // este teléfono queda invalidado de inmediato.
        Otp::where('telefono', $telefono)
            ->whereNull('verificado_at')
            ->update(['expira_at' => now()]);

        $codigo = $this->generarCodigo();

        Otp::create([
            'telefono' => $telefono,
            'codigo_hash' => bcrypt($codigo),
            'expira_at' => now()->addMinutes((int) config('fullpinta.otp.expira_minutos')),
        ]);

        $this->enviador->enviar($telefono, $codigo);
    }

    private function generarCodigo(): string
    {
        $digitos = (int) config('fullpinta.otp.digitos');
        $min = (int) str_pad('1', $digitos, '0');
        $max = (int) str_pad('', $digitos, '9');

        return (string) random_int($min, $max);
    }
}
