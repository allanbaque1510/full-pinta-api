<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarConsentimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'finalidad' => [
                'required',
                'string',
                'in:operacion_servicio,comunicaciones_transaccionales,marketing,transferencia_internacional',
            ],
            'otorgado' => ['required', 'boolean'],
        ];
    }
}
