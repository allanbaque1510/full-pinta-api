<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivarSuscripcionRequest extends FormRequest
{
    /** Propiedad puntual: solo el dueño legal del negocio, ni siquiera `admin` (§3.2). */
    public function authorize(): bool
    {
        return $this->route('negocio')->propietario_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::exists('plan', 'codigo')],
            'profesionales' => ['required', 'integer', 'min:1'],
            'ciclo' => ['required', Rule::in(['mensual', 'anual'])],
        ];
    }
}
