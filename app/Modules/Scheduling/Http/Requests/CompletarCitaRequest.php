<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompletarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionar', $this->route('cita'));
    }

    public function rules(): array
    {
        return [
            // 100% al profesional y NO es base de comisión (§4.7).
            'propina' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
