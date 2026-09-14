<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;
use App\Modules\Identity\Application\Exceptions\DemasiadosIntentosLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\NewAccessToken;

/**
 * Login por correo/contraseña (§4.3). Rate limit por correo, mismo criterio
 * que `SolicitarOtp` para el teléfono — sin esto, alguien puede probar
 * contraseñas sin límite contra un correo conocido.
 */
final readonly class IniciarSesionConEmail
{
    public function __construct(private LimitarSesionesActivas $limitarSesiones) {}

    /**
     * @return array{0: Usuario, 1: NewAccessToken}
     */
    public function __invoke(string $email, string $password): array
    {
        $clave = "login:{$email}";
        $maximo = (int) config('fullpinta.login.max_intentos_por_ventana');
        $ventanaSegundos = (int) config('fullpinta.login.ventana_minutos') * 60;

        if (RateLimiter::tooManyAttempts($clave, $maximo)) {
            throw new DemasiadosIntentosLogin(RateLimiter::availableIn($clave));
        }

        $usuario = Usuario::where('email', $email)->first();

        if ($usuario === null || ! Hash::check($password, $usuario->password_hash)) {
            RateLimiter::hit($clave, $ventanaSegundos);

            throw_validacion('El correo o la contraseña no son correctos.', 'password');
        }

        RateLimiter::clear($clave);

        ($this->limitarSesiones)($usuario);

        return [$usuario, $usuario->createToken('email')];
    }
}
