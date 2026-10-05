<?php

namespace Database\Seeders;

use App\Models\Permiso;
use Illuminate\Database\Seeder;

/**
 * Un permiso por RUTA protegida por rol (§3.2) — nunca dos rutas comparten
 * el mismo código, aunque hoy exijan el mismo rol: son reglas independientes
 * que el mantenedor de permisos puede divergir mañana sin tocar código.
 *
 * El código es, a propósito, el mismo string que el `name()` de su ruta en
 * el `routes.php` correspondiente — un solo grep conecta ruta ↔ permiso.
 *
 * Lo que NO está acá: las rutas que dependen de propiedad puntual (es tu
 * propia cita, eres tú mismo el profesional, eres el dueño legal del
 * negocio) — esas no pasan por `rol_permiso`, ver docblock de la migración
 * `crear_tabla_permisos` y de cada Policy.
 */
class PermisoSeeder extends Seeder
{
    /**
     * codigo => [nombre, descripción breve y específica].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PERMISOS = [
        // Negocio
        'negocios.show' => ['Ver negocio', 'Ver los datos del negocio.'],
        'negocios.update' => ['Editar negocio', 'Editar el nombre de marca, RUC y portada del negocio.'],
        'negocios.locales.index' => ['Ver locales del negocio', 'Listar todos los locales de un negocio.'],
        'negocios.locales.store' => ['Crear local', 'Crear un nuevo local dentro del negocio.'],
        'negocios.miembros.index' => ['Ver miembros del negocio', 'Listar quién tiene acceso administrativo/recepción al negocio.'],
        'negocios.miembros.store' => ['Agregar miembro', 'Agregar un admin o recepcionista por teléfono al negocio.'],
        'miembros.terminar' => ['Terminar membresía', 'Revocar el acceso de un miembro del negocio (sin borrar el historial).'],

        // Local
        'locales.show' => ['Ver local', 'Ver los datos internos de un local.'],
        'locales.update' => ['Editar local', 'Editar nombre, dirección, ubicación y configuración del local.'],
        'locales.activar' => ['Activar local', 'Publicar un local en borrador para que sea visible al público.'],
        'locales.pausar' => ['Pausar local', 'Pausar temporalmente un local activo.'],

        // Horarios
        'locales.horarios.index' => ['Ver horarios', 'Ver el horario semanal de atención del local.'],
        'locales.horarios.store' => ['Crear horario', 'Agregar un bloque de horario de atención.'],
        'horarios.update' => ['Editar horario', 'Editar un bloque de horario de atención existente.'],
        'horarios.destroy' => ['Eliminar horario', 'Eliminar un bloque de horario de atención.'],

        // Amenidades
        'locales.amenidades.index' => ['Ver amenidades', 'Ver las amenidades configuradas del local.'],
        'locales.amenidades.update' => ['Editar amenidades', 'Reemplazar el conjunto de amenidades del local.'],

        // Imágenes del local
        'locales.imagenes.index' => ['Ver galería del local', 'Ver la galería de fotos del local.'],
        'locales.imagenes.store' => ['Agregar foto al local', 'Subir una foto a la galería del local.'],

        // Servicios
        'locales.servicios.index' => ['Ver servicios', 'Ver el catálogo de servicios que ofrece el local.'],
        'locales.servicios.store' => ['Crear servicio', 'Agregar un servicio del catálogo maestro al local, con su precio.'],
        'servicios.update' => ['Editar servicio', 'Editar precio, duración o buffer de un servicio del local.'],
        'servicios.destroy' => ['Desactivar servicio', 'Desactivar un servicio del local.'],
        'servicios.tamanos.update' => ['Editar precios por tamaño', 'Editar precio/duración por tamaño de mascota de un servicio (grooming).'],

        // Productos
        'locales.productos.index' => ['Ver productos', 'Ver el catálogo de productos del local.'],
        'locales.productos.store' => ['Crear producto', 'Agregar un producto (pomada, cera, shampoo...) al local.'],
        'productos.update' => ['Editar producto', 'Editar precio o comisión de un producto del local.'],
        'productos.destroy' => ['Desactivar producto', 'Desactivar un producto del local.'],

        // Solicitudes de catálogo
        'locales.solicitudes-catalogo.index' => ['Ver solicitudes de catálogo', 'Ver las solicitudes del local para agregar un servicio nuevo al catálogo maestro.'],
        'locales.solicitudes-catalogo.store' => ['Solicitar servicio al catálogo', 'Pedir que la plataforma agregue un servicio que falta en el catálogo maestro.'],

        // Asignaciones
        'locales.asignaciones.index' => ['Ver asignaciones', 'Ver qué profesionales están asignados al local.'],
        'locales.asignaciones.store' => ['Crear asignación', 'Asignar un profesional existente a este local.'],
        'asignaciones.update' => ['Editar asignación', 'Editar rol, modalidad o comisión de una asignación.'],
        'asignaciones.terminar' => ['Terminar asignación', 'Terminar el vínculo laboral de un profesional con el local.'],

        // Turnos
        'locales.asignaciones.turnos.index' => ['Ver turnos', 'Ver los turnos recurrentes de una asignación.'],
        'locales.asignaciones.turnos.store' => ['Crear turno', 'Crear un turno recurrente para una asignación.'],
        'locales.turnos.update' => ['Editar turno', 'Editar un turno recurrente existente.'],
        'locales.turnos.destroy' => ['Eliminar turno', 'Eliminar un turno recurrente.'],

        // Recursos
        'locales.recursos.index' => ['Ver recursos', 'Ver las sillas/mesas (recursos físicos) del local.'],
        'locales.recursos.store' => ['Crear recurso', 'Agregar una silla o mesa nueva al local.'],
        'locales.recursos.update' => ['Editar recurso', 'Editar o dar de baja un recurso del local.'],
        'locales.recursos.destroy' => ['Desactivar recurso', 'Desactivar un recurso del local.'],

        // Excepciones (local y recurso)
        'locales.excepciones.index' => ['Ver excepciones del local', 'Ver los bloqueos de agenda del local (feriados, mantenimiento).'],
        'locales.excepciones.store' => ['Bloquear agenda del local', 'Crear una excepción que bloquea la agenda del local.'],
        'locales.recursos.excepciones.index' => ['Ver excepciones del recurso', 'Ver los bloqueos de agenda de un recurso específico.'],
        'locales.recursos.excepciones.store' => ['Bloquear agenda del recurso', 'Crear una excepción que bloquea un recurso específico.'],

        // Profesionales (desde el local)
        'locales.profesionales.index' => ['Ver profesionales del local', 'Listar los profesionales con asignación vigente en el local.'],
        'locales.profesionales.store' => ['Crear profesional', 'Dar de alta un profesional nuevo con su primera asignación.'],

        // Ficha del cliente
        'locales.clientes.index' => ['Ver clientes del local', 'Ver la bandeja mensual de clientes que visitaron el local.'],
        'locales.clientes.show' => ['Ver ficha de cliente', 'Ver la ficha individual de un cliente en este local.'],
        'locales.clientes.update' => ['Editar ficha de cliente', 'Editar la nota o profesional preferido de un cliente en este local.'],

        // Lista de espera
        'locales.esperas.index' => ['Ver lista de espera', 'Ver quién está en lista de espera por un horario del local.'],

        // Agenda / citas
        'locales.citas.index' => ['Ver agenda del local', 'Ver todas las citas agendadas en el local.'],
        'locales.citas.store' => ['Agendar cita', 'Reservar una cita para sí mismo en el local.'],
        'locales.citas.walk-in.store' => ['Registrar walk-in', 'Registrar un cliente sin cita previa y cobrar el servicio.'],
        'citas.show' => ['Ver una cita', 'Ver el detalle de una cita puntual (lado staff del local).'],
        'citas.confirmar' => ['Confirmar cita', 'Confirmar el hold de una cita (lado staff del local).'],
        'citas.cancelar' => ['Cancelar cita', 'Cancelar una cita (lado staff del local).'],
        'citas.reagendar' => ['Reagendar cita', 'Reagendar una cita a otro horario (lado staff del local).'],
        'citas.iniciar' => ['Marcar en curso', 'Marcar que el cliente llegó y la cita está en curso (lado staff del local).'],
        'citas.completar' => ['Completar cita', 'Marcar una cita como completada (lado staff del local).'],
        'citas.no-show' => ['Marcar no-show', 'Marcar que el cliente no se presentó (lado staff del local).'],
        'citas.productos.store' => ['Agregar producto a cita', 'Agregar un producto vendido dentro de una cita (lado staff del local).'],

        // Reseñas
        'locales.resenas.index' => ['Ver reseñas del local', 'Listar las reseñas recibidas por el local.'],
        'resenas.responder' => ['Responder reseña', 'Publicar la respuesta del local a una reseña de un cliente.'],

        // Liquidaciones
        'locales.liquidaciones.index' => ['Ver liquidaciones', 'Ver las liquidaciones de comisiones de todos los profesionales del local.'],
        'locales.liquidaciones.store' => ['Generar liquidación', 'Generar el borrador de liquidación de un profesional para un periodo.'],
        'liquidaciones.cerrar' => ['Cerrar liquidación', 'Cerrar una liquidación de comisiones en borrador.'],
        'liquidaciones.marcar-pagada' => ['Marcar liquidación pagada', 'Marcar una liquidación cerrada como pagada.'],

        // Profesional (ficha propia, administrada por su(s) local(es))
        'profesionales.show' => ['Ver profesional', 'Ver la ficha completa de un profesional.'],
        'profesionales.update' => ['Editar profesional', 'Editar la ficha de un profesional (bio, alias, foto, traslado).'],
        'profesionales.vincular-cuenta' => ['Vincular cuenta de profesional', 'Vincular la cuenta de usuario de un profesional para que pueda loguearse.'],
        'profesionales.habilidades.index' => ['Ver habilidades', 'Ver qué servicios sabe atender un profesional.'],
        'profesionales.habilidades.destroy' => ['Eliminar habilidad', 'Quitarle a un profesional la habilidad de atender un servicio.'],
        'profesionales.excepciones.index' => ['Ver excepciones del profesional', 'Ver las ausencias/bloqueos de un profesional.'],
        'profesionales.excepciones.store' => ['Bloquear horario de profesional', 'Crear una excepción que bloquea el horario de un profesional en todos sus locales.'],
        'profesionales.turno-fechas.index' => ['Ver overrides de turno', 'Ver los overrides puntuales por fecha del turno de un profesional.'],
        'profesionales.turno-fechas.destroy' => ['Eliminar override de turno', 'Eliminar un override puntual por fecha.'],
        'profesionales.imagenes.index' => ['Ver galería del profesional', 'Ver el portafolio de fotos de un profesional.'],
        'profesionales.imagenes.store' => ['Agregar foto al profesional', 'Subir una foto al portafolio de un profesional.'],
        'profesionales.habilidades.store' => ['Agregar habilidad', 'Autorizar a un profesional a atender un servicio del local.'],
        'profesionales.turno-fechas.store' => ['Crear override de turno', 'Crear un override puntual por fecha sobre el turno recurrente.'],
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $codigo => [$nombre, $descripcion]) {
            Permiso::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'descripcion' => $descripcion, 'activo' => true],
            );
        }
    }
}
