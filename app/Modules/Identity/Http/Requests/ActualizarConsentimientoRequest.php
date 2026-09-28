<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                Rule::exists('finalidad_consentimiento', 'codigo')->where('activo', true),
            ],
            'otorgado' => ['required', 'boolean'],
        ];
    }
}
