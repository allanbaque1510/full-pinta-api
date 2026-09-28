<?php

namespace App\Modules\Identity\Application;

use App\Models\FinalidadConsentimiento;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `FinalidadConsentimiento` (§13.1): de solo
 * lectura para el front por ahora — el catálogo lo define la plataforma.
 */
final readonly class FinalidadConsentimientoService
{
    /**
     * El catálogo completo, activo, con su documento legal vigente embebido
     * (si tiene uno asignado y ya existe una versión publicada), para que el
     * front pinte la pantalla de consentimiento sin hardcodear texto.
     */
    public function listar(): Collection
    {
        return FinalidadConsentimiento::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->get();
    }
}
