<?php

namespace App\Modules\Identity\Application;

use App\Models\Consentimiento;
use App\Models\Usuario;

/**
 * Un único punto de entrada para "el usuario tocó el switch de una finalidad":
 * decide si eso es otorgar o revocar y delega en el caso de uso que
 * corresponde. El controlador llama a este servicio, no a los dos de abajo
 * directamente — la decisión de cuál usar es lógica, no HTTP.
 */
final readonly class ActualizarConsentimiento
{
    public function __construct(
        private OtorgarConsentimiento $otorgar,
        private RevocarConsentimiento $revocar,
    ) {}

    public function __invoke(
        Usuario $usuario,
        string $finalidad,
        bool $otorgado,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Consentimiento|array {
        if ($otorgado) {
            return ($this->otorgar)($usuario, $finalidad, origen: 'app', ip: $ip, userAgent: $userAgent);
        }

        $revocado = ($this->revocar)($usuario, $finalidad);

        // Pidieron revocar algo que nunca se otorgó: no es un error — el
        // estado final que el front pedía ya es el real — pero tampoco hay
        // una fila que devolver.
        return $revocado ?? ['finalidad' => $finalidad, 'otorgado' => false, 'vigente' => false];
    }
}
