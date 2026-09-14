<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cualquier usuario autenticado puede reservar para sí mismo.
        return true;
    }

    public function rules(): array
    {
        return [
            'profesional_id' => ['required', 'uuid', Rule::exists('profesional', 'id')],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['uuid', Rule::exists('servicio_local', 'id')->where('activo', true)],
            'recurso_id' => ['nullable', 'uuid', Rule::exists('recurso', 'id')->where('activo', true)],
            'inicio' => ['required', 'date'],
            'para_tipo' => ['sometimes', 'in:titular,otra_persona,mascota'],
            'para_nombre' => ['nullable', 'string', 'max:255', 'required_if:para_tipo,otra_persona'],
            'mascota_id' => ['nullable', 'uuid', Rule::exists('mascota', 'id'), 'required_if:para_tipo,mascota'],
            'nota_cliente' => ['nullable', 'string'],
        ];
    }
}
