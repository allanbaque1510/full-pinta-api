<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\FinalidadConsentimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinalidadConsentimiento
 */
class FinalidadConsentimientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $documento = $this->documentoVigente();

        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'obligatorio' => $this->obligatorio,
            'documento_legal' => $documento === null ? null : [
                'tipo' => $documento->tipo,
                'version' => $documento->version,
                'contenido' => $documento->contenido,
                'url' => $documento->url,
            ],
        ];
    }
}
