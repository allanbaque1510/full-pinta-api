<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

/**
 * Cambiar la contraseña conociendo la actual (§4.3) — a diferencia de
 * `RestablecerContrasena` (se perdió el acceso, se prueba identidad por
 * código), aquí la sesión ya es de quien dice ser: no hace falta revocar
 * nada más, ni un código nuevo.
 */
final readonly class CambiarContrasena
{
    public function __invoke(Usuario $usuario, string $actual, string $nueva): void
    {
        if (! Hash::check($actual, $usuario->password_hash)) {
            throw_validacion('La contraseña actual no es correcta.', 'actual');
        }

        $usuario->update(['password_hash' => Hash::make($nueva)]);
    }
}
