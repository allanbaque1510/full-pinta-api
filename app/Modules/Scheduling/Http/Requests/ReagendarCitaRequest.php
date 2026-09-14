<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReagendarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reagendar', $this->route('cita'));
    }

    public function rules(): array
    {
        return [
            'profesional_id' => ['required', 'uuid', Rule::exists('profesional', 'id')],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['uuid', Rule::exists('servicio_local', 'id')->where('activo', true)],
            'recurso_id' => ['nullable', 'uuid', Rule::exists('recurso', 'id')->where('activo', true)],
            'inicio' => ['required', 'date'],
        ];
    }
}
