<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarProfesionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actualizar', $this->route('profesional'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'alias' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'foto_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'independiente' => ['sometimes', 'boolean'],
            'perfil_publico' => ['sometimes', 'boolean'],
            'traslado_min' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
