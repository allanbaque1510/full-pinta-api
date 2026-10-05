<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Qué permiso (uno por ruta) tiene cada rol — estado inicial derivado del
 * comportamiento ya probado del §3.2, ahora en filas independientes: dos
 * rutas que hoy exigen el mismo rol pueden divergir mañana con un solo
 * `UPDATE` acá (o desde el mantenedor), sin tocar código.
 *
 * Varios permisos de la matriz (ver agenda propia, bloquear su propio
 * horario, ver sus propias comisiones, completar/no-show sobre SU PROPIA
 * cita) no aparecen abajo para `profesional`: esos ya los tiene vía
 * propiedad puntual (`esElMismo`/`esElProfesionalAsignado` en la Policy
 * correspondiente), no vía esta tabla — ver docblock de cada Policy.
 *
 * Idempotente con `sync()`: correrlo de nuevo deja cada rol con exactamente
 * los permisos listados aquí abajo, ni más ni menos.
 */
class RolPermisoSeeder extends Seeder
{
    /** Cualquier miembro con rol vigente en el negocio/local — propietario, admin o recepción. */
    private const VER_INTERNO = [
        'locales.show',
        'locales.horarios.index',
        'locales.amenidades.index',
        'locales.imagenes.index',
        'locales.servicios.index',
        'locales.productos.index',
        'locales.solicitudes-catalogo.index',
        'locales.asignaciones.index',
        'locales.asignaciones.turnos.index',
        'locales.recursos.index',
        'locales.excepciones.index',
        'locales.recursos.excepciones.index',
        'locales.profesionales.index',
        'locales.esperas.index',
        'locales.citas.index',
        'locales.resenas.index',
        'negocios.locales.index',
        'profesionales.show',
        'profesionales.habilidades.index',
        'profesionales.excepciones.index',
        'profesionales.turno-fechas.index',
        'profesionales.imagenes.index',
        'citas.show',
    ];

    /** Agenda completa del local: propietario, admin y recepción (§3.2). */
    private const AGENDA_COMPLETA = [
        'locales.clientes.index',
        'locales.clientes.show',
        'locales.clientes.update',
        'citas.confirmar',
        'citas.cancelar',
        'citas.reagendar',
        'citas.iniciar',
        'citas.completar',
        'citas.no-show',
        'citas.productos.store',
    ];

    /** "Editar precios, servicios, asignaciones" — propietario y admin (§3.2). */
    private const ADMINISTRAR = [
        'negocios.show',
        'negocios.update',
        'negocios.locales.store',
        'negocios.miembros.index',
        'negocios.miembros.store',
        'miembros.terminar',
        'locales.update',
        'locales.activar',
        'locales.pausar',
        'locales.horarios.store',
        'horarios.update',
        'horarios.destroy',
        'locales.amenidades.update',
        'locales.imagenes.store',
        'locales.servicios.store',
        'servicios.update',
        'servicios.destroy',
        'servicios.tamanos.update',
        'locales.productos.store',
        'productos.update',
        'productos.destroy',
        'locales.solicitudes-catalogo.store',
        'locales.asignaciones.store',
        'asignaciones.update',
        'asignaciones.terminar',
        'locales.asignaciones.turnos.store',
        'locales.turnos.update',
        'locales.turnos.destroy',
        'locales.recursos.store',
        'locales.recursos.update',
        'locales.recursos.destroy',
        'locales.excepciones.store',
        'locales.recursos.excepciones.store',
        'locales.profesionales.store',
        'profesionales.update',
        'profesionales.vincular-cuenta',
        'profesionales.habilidades.destroy',
        'profesionales.excepciones.store',
        'profesionales.turno-fechas.destroy',
        'profesionales.imagenes.store',
        'profesionales.habilidades.store',
        'profesionales.turno-fechas.store',
        'locales.liquidaciones.index',
        'locales.liquidaciones.store',
        'liquidaciones.cerrar',
        'liquidaciones.marcar-pagada',
        'resenas.responder',
    ];

    /** "Agendar para sí" — los cinco roles de la matriz (§3.2). */
    private const AGENDAR_PARA_SI = ['locales.citas.store'];

    /** "Crear walk-in / registrar cobro" — profesional, recepción, propietario y admin (§3.2). */
    private const WALK_IN = ['locales.citas.walk-in.store'];

    public function run(): void
    {
        $matriz = [
            'cliente' => [...self::AGENDAR_PARA_SI],
            'profesional' => [...self::AGENDAR_PARA_SI, ...self::WALK_IN],
            'recepcion' => [...self::AGENDAR_PARA_SI, ...self::WALK_IN, ...self::VER_INTERNO, ...self::AGENDA_COMPLETA],
            'admin' => [...self::AGENDAR_PARA_SI, ...self::WALK_IN, ...self::VER_INTERNO, ...self::AGENDA_COMPLETA, ...self::ADMINISTRAR],
            'propietario' => [...self::AGENDAR_PARA_SI, ...self::WALK_IN, ...self::VER_INTERNO, ...self::AGENDA_COMPLETA, ...self::ADMINISTRAR],
        ];

        foreach ($matriz as $codigoRol => $codigosPermiso) {
            $rol = Rol::where('codigo', $codigoRol)->first();

            if ($rol === null) {
                continue;
            }

            $permisoIds = Permiso::whereIn('codigo', array_unique($codigosPermiso))->pluck('id');

            $rol->permisos()->sync($permisoIds);
        }
    }
}
