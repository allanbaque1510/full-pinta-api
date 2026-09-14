<?php

namespace App\Modules\Staffing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TerminarAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarCatalogo', $this->route('asignacion')->local);
    }

    public function rules(): array
    {
        return [
            'hasta' => ['sometimes', 'date'],
        ];
    }
}
