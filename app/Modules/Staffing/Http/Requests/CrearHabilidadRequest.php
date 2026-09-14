<?php

namespace App\Modules\Staffing\Http\Requests;

use App\Models\ServicioLocal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearHabilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $servicio = ServicioLocal::find($this->input('servicio_local_id'));

        return $servicio !== null && $this->user()->can('gestionarCatalogo', $servicio->local);
    }

    public function rules(): array
    {
        return [
            'servicio_local_id' => ['required', 'uuid', Rule::exists('servicio_local', 'id')],
            'precio_override' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
