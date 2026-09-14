<?php

namespace App\Modules\Staffing\Http\Requests;

use App\Models\Local;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearTurnoFechaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $local = Local::find($this->input('local_id'));

        return $local !== null && $this->user()->can('gestionarCatalogo', $local);
    }

    public function rules(): array
    {
        return [
            'local_id' => ['required', 'uuid', Rule::exists('local', 'id')],
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'in:extra,reemplaza,cancela'],
            'entra' => ['nullable', 'date_format:H:i'],
            'sale' => ['nullable', 'date_format:H:i'],
            'nota' => ['nullable', 'string'],
        ];
    }
}
