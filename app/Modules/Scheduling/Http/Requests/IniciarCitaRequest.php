<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;

class IniciarCitaRequest extends FormRequest
{
    /** Marcar que llegó: quien opera la agenda del local, o el propio profesional asignado. */
    public function authorize(): bool
    {
        $cita = $this->route('cita')->loadMissing('local');

        return $this->user()->profesional?->id === $cita->profesional_id
            || (new ContextoAcceso($this->user()))->tienePermiso($cita->local, 'citas.iniciar');
    }

    public function rules(): array
    {
        return [];
    }
}
