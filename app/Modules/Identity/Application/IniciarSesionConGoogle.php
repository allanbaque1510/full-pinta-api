<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;
use App\Modules\Identity\Application\Contracts\VerificadorTokenGoogle;
use App\Support\ImagenService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Login/registro con Google (§4.3). El teléfono sigue siendo obligatorio
 * para cualquier cuenta — Google no lo da, así que el front lo pide en el
 * mismo formulario. La verificación de ese teléfono es la misma de siempre:
 * el flujo de OTP ya existente (`VerificarOtp`), no algo nuevo que este caso
 * de uso resuelva.
 *
 * Resolución del usuario, en orden:
 * 1. Ya existe por `google_id` → login directo.
 * 2. No existe por `google_id`, pero el email ya es de otra cuenta (creada
 *    por OTP o por correo) → se enlaza `google_id` a esa cuenta — el token
 *    de Google ya probó que la persona es dueña de ese correo, así que
 *    enlazar es seguro. El teléfono del body se ignora: esa cuenta ya tiene uno.
 * 3. Ninguno existe → se crea una cuenta nueva con el teléfono del body.
 */
final readonly class IniciarSesionConGoogle
{
    public function __construct(
        private VerificadorTokenGoogle $verificador,
        private OtorgarConsentimiento $otorgarConsentimiento,
        private LimitarSesionesActivas $limitarSesiones,
        private ImagenService $imagenes,
    ) {}

    /**
     * @return array{0: Usuario, 1: NewAccessToken}
     */
    public function __invoke(string $idToken, string $telefono): array
    {
        $claims = $this->verificador->verificar($idToken);

        $usuario = Usuario::where('google_id', $claims['sub'])->first();

        if ($usuario === null) {
            $usuario = Usuario::where('email', $claims['email'])->first();

            $usuario = $usuario !== null
                ? tap($usuario)->update([
                    'google_id' => $claims['sub'],
                    // El token de Google ya prueba propiedad del correo — si
                    // Google lo marca verificado y la cuenta todavía no lo
                    // estaba, se actualiza de paso. Nunca se degrada a false
                    // (un correo ya verificado por otro medio se queda así).
                    'email_verificado' => $usuario->email_verificado || $claims['email_verified'],
                ])
                : $this->registrar($claims, $telefono);
        }

        ($this->limitarSesiones)($usuario);

        return [$usuario->fresh(), $usuario->createToken('google')];
    }

    /**
     * @param  array{sub: string, email: string, email_verified: bool, name: string, picture: ?string}  $claims
     */
    private function registrar(array $claims, string $telefono): Usuario
    {
        if (Usuario::where('telefono', $telefono)->exists()) {
            throw_validacion('Ese teléfono ya está en uso por otra cuenta.', 'telefono');
        }

        $usuario = Usuario::create([
            'telefono' => $telefono,
            'telefono_verificado' => false,
            'google_id' => $claims['sub'],
            'email' => $claims['email'],
            // A diferencia de OTP/correo, acá sí hay una fuente real: Google
            // ya verificó este correo antes de emitir el token.
            'email_verificado' => $claims['email_verified'],
            'nombre' => $claims['name'] !== '' ? $claims['name'] : 'Usuario de Google',
            // Igual que OTP: no se autentica por contraseña, este hash solo
            // saca a la fila del estado "cliente sombra sin reclamar".
            'password_hash' => Hash::make(Str::random(40)),
        ]);

        if ($claims['picture'] !== null) {
            $this->imagenes->establecerFotoPerfil($usuario, 'usuario', $claims['picture']);
        }

        ($this->otorgarConsentimiento)($usuario, 'operacion_servicio', origen: 'app');

        return $usuario;
    }
}
