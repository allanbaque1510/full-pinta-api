<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgregarProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cita = $this->route('cita')->loadMissing('local');

        return $this->user()->profesional?->id === $cita->profesional_id
            || (new ContextoAcceso($this->user()))->tienePermiso($cita->local, 'citas.productos.store');
    }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'uuid', Rule::exists('producto', 'id')->where('activo', true)],
            'cantidad' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
