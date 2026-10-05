<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearExcepcionProfesionalRequest extends FormRequest
{
    // Autorización: combinada en el controller (quien administra o el propio profesional).

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
