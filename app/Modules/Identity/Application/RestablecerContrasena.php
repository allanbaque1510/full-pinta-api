<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

/**
 * Confirma el código de `SolicitarRecuperacionContrasena` y fija una
 * contraseña nueva. Sirve igual para una cuenta que nunca tuvo una contraseña
 * real (registrada solo por OTP): completarla por este camino es válido, no
 * un caso de borde — el resultado es la misma cuenta, con un método de acceso
 * más.
 *
 * Verificar el canal `email` de paso marca `email_verificado`: recibir y
 * escribir ese código ya prueba la propiedad del correo, es el mismo hecho
 * que `ConfirmarVerificacionEmail` comprueba por separado.
 */
final readonly class RestablecerContrasena
{
    public function __construct(
        private CodigoVerificacion $codigos,
        private LimitarSesionesActivas $limitarSesiones,
    ) {}

    /**
     * @return array{0: Usuario, 1: NewAccessToken}
     */
    public function __invoke(string $canal, ?string $telefono, ?string $email, string $codigo, string $password): array
    {
        if ($canal === 'whatsapp') {
            $this->codigos->verificarTelefono($telefono, $codigo);
            $usuario = Usuario::where('telefono', $telefono)->first();
        } else {
            $this->codigos->verificarEmail($email, $codigo);
            $usuario = Usuario::where('email', $email)->first();
        }

        // El código era válido pero no correspondía a ninguna cuenta: mismo
        // mensaje genérico que un código incorrecto, por la misma razón que
        // en `SolicitarRecuperacionContrasena` — no confirmar qué existe.
        if ($usuario === null) {
            throw_validacion('El código no es válido o ya expiró. Solicita uno nuevo.', 'codigo');
        }

        $usuario->update([
            'password_hash' => Hash::make($password),
            ...($canal === 'email' ? ['email_verificado' => true] : []),
        ]);

        // Recuperar el acceso es exactamente el escenario en que no se sabe
        // quién más pudo quedar autenticado: se revoca todo, mismo criterio
        // que `AnonimizarUsuario`.
        $usuario->tokens()->delete();

        ($this->limitarSesiones)($usuario);

        return [$usuario->fresh(), $usuario->createToken('recuperacion_contrasena')];
    }
}
