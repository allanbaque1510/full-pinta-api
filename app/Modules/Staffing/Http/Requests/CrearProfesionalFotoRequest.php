<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearProfesionalFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actualizar', $this->route('profesional'));
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
