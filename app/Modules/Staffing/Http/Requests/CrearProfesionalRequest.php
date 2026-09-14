<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearProfesionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'foto_url' => ['nullable', 'url', 'max:2048'],
            'independiente' => ['sometimes', 'boolean'],
            'perfil_publico' => ['sometimes', 'boolean'],
            'traslado_min' => ['sometimes', 'integer', 'min:0'],

            'rol' => ['required', 'in:barbero,estilista,manicurista,groomer,recepcion'],
            'modalidad' => ['required', 'in:empleado,renta_silla,invitado'],
            'comision_pct' => ['required', 'numeric', 'between:0,100'],
            'desde' => ['sometimes', 'date'],
        ];
    }
}
