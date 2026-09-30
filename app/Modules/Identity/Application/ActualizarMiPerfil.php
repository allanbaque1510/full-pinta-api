<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;
use App\Support\ImagenService;

/**
 * Autogestión del propio perfil (§4.3): `nombre`/`género`/`fecha_nacimiento`/
 * foto — lo que es de la persona, no de su relación con el marketplace
 * (`telefono`/`email` tienen sus propios flujos de verificación, no se tocan
 * acá). Antes no existía ningún endpoint para esto — `CuentaController` solo
 * tenía el derecho de eliminación.
 */
final readonly class ActualizarMiPerfil
{
    public function __construct(private ImagenService $imagenes) {}

    /**
     * @param  array{nombre?: string, genero?: ?string, fecha_nacimiento?: ?string, foto_url?: ?string}  $datos
     */
    public function __invoke(Usuario $usuario, array $datos): Usuario
    {
        if (array_key_exists('foto_url', $datos)) {
            $this->imagenes->establecerFotoPerfil($usuario, 'usuario', $datos['foto_url']);
            unset($datos['foto_url']);
        }

        if ($datos !== []) {
            $usuario->update($datos);
        }

        return $usuario->fresh()->loadMissing('fotoPerfil');
    }
}
