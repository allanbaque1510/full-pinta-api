<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('confirmar', $this->route('cita'));
    }

    public function rules(): array
    {
        return [];
    }
}
