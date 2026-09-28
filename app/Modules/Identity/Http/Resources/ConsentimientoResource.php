<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\Consentimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Consentimiento
 */
class ConsentimientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'finalidad' => $this->finalidadConsentimiento->codigo,
            'otorgado' => $this->otorgado,
            'vigente' => $this->estaVigente(),
            'documento_legal' => $this->when($this->documento_legal_id !== null, fn () => [
                'tipo' => $this->documentoLegal->tipo,
                'version' => $this->documentoLegal->version,
            ]),
            'otorgado_at' => $this->otorgado_at,
            'revocado_at' => $this->revocado_at,
        ];
    }
}
