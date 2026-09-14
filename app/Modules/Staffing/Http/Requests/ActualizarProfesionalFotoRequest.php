<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarProfesionalFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actualizar', $this->route('foto')->profesional);
    }

    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
