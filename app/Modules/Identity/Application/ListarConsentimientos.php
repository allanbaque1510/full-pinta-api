<?php

namespace App\Modules\Identity\Application;

use App\Models\Consentimiento;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * El estado más reciente de cada finalidad que el usuario tocó alguna vez
 * (§13.1). Una finalidad ausente del resultado significa que nunca se le
 * preguntó al usuario por ella.
 */
final readonly class ListarConsentimientos
{
    public function __invoke(Usuario $usuario): Collection
    {
        return Consentimiento::where('usuario_id', $usuario->id)
            ->orderByDesc('otorgado_at')
            ->get()
            ->unique('finalidad')
            ->values();
    }
}
