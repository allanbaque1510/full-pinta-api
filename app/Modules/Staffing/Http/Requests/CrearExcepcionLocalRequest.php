<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearExcepcionLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'motivo' => ['required', 'in:feriado,vacaciones,mantenimiento,personal,bloqueo_manual'],
            'nota' => ['nullable', 'string'],
        ];
    }
}
