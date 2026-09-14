<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgregarProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionar', $this->route('cita'));
    }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'uuid', Rule::exists('producto', 'id')->where('activo', true)],
            'cantidad' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
