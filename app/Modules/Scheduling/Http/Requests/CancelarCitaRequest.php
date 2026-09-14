<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancelar', $this->route('cita'));
    }

    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
