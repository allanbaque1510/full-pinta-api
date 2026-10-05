<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Suma un profesional que YA EXISTE a un local nuevo. Para dar de alta uno
 * desde cero, ver `CrearProfesionalRequest`.
 */
class CrearAsignacionRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'profesional_id' => ['required', 'uuid', Rule::exists('profesional', 'id')],
            'rol' => ['required', 'in:barbero,estilista,manicurista,groomer,recepcion'],
            'modalidad' => ['required', 'in:empleado,renta_silla,invitado'],
            'comision_pct' => ['required', 'numeric', 'between:0,100'],
            'desde' => ['sometimes', 'date'],
        ];
    }
}
