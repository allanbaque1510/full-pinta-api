<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;

class CancelarCitaRequest extends FormRequest
{
    /** El cliente cancela lo suyo; el staff cancela cualquier cita de su local. */
    public function authorize(): bool
    {
        $cita = $this->route('cita')->loadMissing('local');

        return $cita->cliente_id === $this->user()->id
            || (new ContextoAcceso($this->user()))->tienePermiso($cita->local, 'citas.cancelar');
    }

    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
