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
            'finalidad' => $this->finalidad,
            'otorgado' => $this->otorgado,
            'vigente' => $this->estaVigente(),
            'documento_version' => $this->documento_version,
            'otorgado_at' => $this->otorgado_at,
            'revocado_at' => $this->revocado_at,
        ];
    }
}
