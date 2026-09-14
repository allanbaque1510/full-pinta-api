<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivarSuscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarSuscripcion', $this->route('negocio'));
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
