<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VincularCuentaProfesionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vincularCuenta', $this->route('profesional'));
    }

    public function rules(): array
    {
        return [
            'telefono' => ['required', 'string', 'regex:/^09\d{8}$/'],
        ];
    }
}
