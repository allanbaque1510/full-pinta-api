<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarWalkInRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registrar un walk-in es una acción de agenda, no de catálogo:
        // recepción también puede (agenda y cobra, §3.2) — no solo
        // propietario/admin.
        return $this->user()->can('ver', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required_without_all:nombre,telefono', 'uuid', Rule::exists('usuario', 'id')],
            'nombre' => ['required_without:cliente_id', 'string', 'max:255'],
            'telefono' => ['required_without:cliente_id', 'string', 'max:20'],
            'profesional_id' => ['required', 'uuid', Rule::exists('profesional', 'id')],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['uuid', Rule::exists('servicio_local', 'id')->where('activo', true)],
            'recurso_id' => ['nullable', 'uuid', Rule::exists('recurso', 'id')->where('activo', true)],
            'inicio' => ['required', 'date'],
        ];
    }
}
