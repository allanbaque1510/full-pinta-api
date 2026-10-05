<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearProfesionalRequest extends FormRequest
{
    // Autorización: middleware `permiso` en routes.php.

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'foto_url' => ['nullable', 'url', 'max:2048'],
            'perfil_publico' => ['sometimes', 'boolean'],
            'traslado_min' => ['sometimes', 'integer', 'min:0'],

            'rol' => ['required', 'in:barbero,estilista,manicurista,groomer,recepcion'],
            'modalidad' => ['required', 'in:empleado,renta_silla,invitado'],
            'comision_pct' => ['required', 'numeric', 'between:0,100'],
            'desde' => ['sometimes', 'date'],

            // Opcional: si ya tiene cuenta, vincula de una vez (§4.6) — resuelve
            // por teléfono de una cuenta YA registrada, sin invitación por link.
            'telefono' => ['sometimes', 'nullable', 'string', 'regex:/^09\d{8}$/'],
        ];
    }
}
