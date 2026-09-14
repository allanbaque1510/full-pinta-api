<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actualizar', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'direccion' => ['sometimes', 'string'],
            'referencia' => ['sometimes', 'nullable', 'string'],
            'lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'numeric', 'between:-180,180'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:20'],
            'lead_time_min' => ['sometimes', 'integer', 'min:0'],
            'horizonte_dias' => ['sometimes', 'integer', 'min:1'],
            'politica_cancelacion_horas' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
