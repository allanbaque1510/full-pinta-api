<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearSolicitudCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('local'));
    }

    public function rules(): array
    {
        return [
            'rubro' => ['required', Rule::exists('rubro', 'codigo')->where('activo', true)],
            'nombre_propuesto' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ];
    }
}
