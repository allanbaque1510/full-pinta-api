<?php

namespace App\Modules\Notifications\Application;

use App\Models\NotificacionCategoria;
use App\Models\PreferenciaNotificacion;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Preferencias por categoría (§4.9, §11): `push`/`whatsapp` default `true` en
 * cada una hasta que el usuario las toque — "se respeta siempre, salvo lo
 * transaccional indispensable", que resuelve `NotificacionService` con la
 * bandera `obligatorio`, no aquí.
 */
final readonly class PreferenciaNotificacionService
{
    /** Una fila por categoría activa, con el default de la tabla si el usuario nunca la tocó. */
    public function listar(Usuario $usuario): Collection
    {
        $categorias = NotificacionCategoria::where('activo', true)->get();
        $existentes = PreferenciaNotificacion::where('usuario_id', $usuario->id)->get()->keyBy('categoria_id');

        return $categorias->map(function (NotificacionCategoria $categoria) use ($existentes, $usuario) {
            $preferencia = $existentes->get($categoria->id) ?? new PreferenciaNotificacion([
                'usuario_id' => $usuario->id,
                'categoria_id' => $categoria->id,
                'push' => true,
                'whatsapp' => true,
            ]);

            return $preferencia->setRelation('categoria', $categoria);
        });
    }

    /**
     * Reemplaza el conjunto completo, igual que `PUT /locales/{local}/amenidades`.
     *
     * @param  array<int, array{categoria: string, push: bool, whatsapp: bool}>  $preferencias
     */
    public function sincronizar(Usuario $usuario, array $preferencias): Collection
    {
        foreach ($preferencias as $datos) {
            $categoria = NotificacionCategoria::where('codigo', $datos['categoria'])->firstOrFail();

            PreferenciaNotificacion::updateOrCreate(
                ['usuario_id' => $usuario->id, 'categoria_id' => $categoria->id],
                ['push' => $datos['push'], 'whatsapp' => $datos['whatsapp']],
            );
        }

        return $this->listar($usuario);
    }
}
