<?php

namespace App\Modules\Reviews\Http\Resources;

use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reporte
 */
class ReporteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'objeto_id' => $this->objeto_id,
            'reportante_id' => $this->reportante_id,
            'motivo' => $this->motivo,
            'detalle' => $this->detalle,
            'estado' => $this->estado,
        ];
    }
}
