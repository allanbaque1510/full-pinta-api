<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarClienteLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nota' => ['sometimes', 'nullable', 'string'],
            'profesional_preferido_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('profesional', 'id')],
        ];
    }
}
