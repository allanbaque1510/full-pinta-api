<?php

namespace App\Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('horario')->local);
    }

    public function rules(): array
    {
        return [
            'dia_semana' => ['sometimes', 'integer', 'between:0,6'],
            'abre' => ['sometimes', 'date_format:H:i'],
            'cierra' => ['sometimes', 'date_format:H:i'],
        ];
    }
}
