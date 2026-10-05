<?php

namespace App\Http\Requests;

use App\Models\Local;
use App\Support\Auth\ContextoAcceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarImagenRequest extends FormRequest
{
    /**
     * El dueño de la imagen se conoce solo en tiempo de ejecución
     * (polimórfico): mismo permiso que agregar una foto nueva a ese dueño,
     * reusado para editar/borrar — no hay una regla distinta para eso.
     */
    public function authorize(): bool
    {
        $dueno = $this->route('imagen')->objeto;
        $contexto = new ContextoAcceso($this->user());

        if ($dueno instanceof Local) {
            return $contexto->tienePermiso($dueno, 'locales.imagenes.store');
        }

        // Profesional: quien administra alguno de sus locales, o él mismo.
        return $contexto->tienePermisoSobreProfesional($dueno, 'profesionales.imagenes.store')
            || $this->user()->profesional?->id === $dueno->id;
    }

    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048'],
            'tipo' => ['sometimes', Rule::exists('tipo_imagen', 'codigo')],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
