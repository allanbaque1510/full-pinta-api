<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('asignacion')->local);
    }

    public function rules(): array
    {
        return [
            'rol' => ['sometimes', 'in:barbero,estilista,manicurista,groomer,recepcion'],
            'modalidad' => ['sometimes', 'in:empleado,renta_silla,invitado'],
            'comision_pct' => ['sometimes', 'numeric', 'between:0,100'],
        ];
    }
}
