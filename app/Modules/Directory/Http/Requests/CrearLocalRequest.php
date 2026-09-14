<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crearLocal', $this->route('negocio'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['required', 'string'],
            'referencia' => ['nullable', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'lead_time_min' => ['sometimes', 'integer', 'min:0'],
            'horizonte_dias' => ['sometimes', 'integer', 'min:1'],
            'politica_cancelacion_horas' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
