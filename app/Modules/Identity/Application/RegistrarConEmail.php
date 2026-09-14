<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

/**
 * Registro por correo/contraseña (§4.3). A diferencia de Google, acá la
 * contraseña la elige quien se registra — nunca debe poder tomar una cuenta
 * ajena solo porque el correo o el teléfono coincidan, así que ambos se
 * validan como únicos en el `FormRequest` (`Rule::unique`), no se enlaza nada.
 *
 * El teléfono es obligatorio y nace sin verificar — se verifica con el mismo
 * flujo de OTP que ya existía (`VerificarOtp`), igual que en `IniciarSesionConGoogle`.
 */
final readonly class RegistrarConEmail
{
    public function __construct(private OtorgarConsentimiento $otorgarConsentimiento) {}

    /**
     * @param  array{nombre: string, email: string, password: string, telefono: string}  $datos
     * @return array{0: Usuario, 1: NewAccessToken}
     */
    public function __invoke(array $datos): array
    {
        $usuario = Usuario::create([
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'password_hash' => Hash::make($datos['password']),
            'telefono' => $datos['telefono'],
            'telefono_verificado' => false,
        ]);

        ($this->otorgarConsentimiento)($usuario, 'operacion_servicio', origen: 'app');

        return [$usuario, $usuario->createToken('email')];
    }
}
