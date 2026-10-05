<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReagendarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cita = $this->route('cita')->loadMissing('local');

        return $cita->cliente_id === $this->user()->id
            || (new ContextoAcceso($this->user()))->tienePermiso($cita->local, 'citas.reagendar');
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
