<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desambigua la palabra "rol": antes tres cosas distintas se llamaban igual
 * (`negocio_miembro.rol`, `asignacion.rol`, y el nuevo catálogo `rol` de
 * permisos — ver `crear_tabla_permisos`). Ni `negocio_miembro` ni `asignacion`
 * son el nivel de acceso a permisos (eso es `rol`/`rol_permiso`): el primero
 * es "qué acceso tiene este usuario a la plataforma" y el segundo es "qué
 * puesto ocupa este profesional" (barbero, estilista...) — por eso los dos
 * pasan a `rol_personal`, y el catálogo de permisos se queda con el nombre
 * corto.
 *
 * El contrato HTTP no cambia: el campo que ve el front sigue llamándose
 * `rol` en cada request/response (`AgregarNegocioMiembroRequest`,
 * `CrearAsignacionRequest`, `NegocioMiembroResource`, `AsignacionResource`,
 * `ResolverContexto`) — la traducción al nombre real de columna pasa por el
 * Service/Resource correspondiente, nunca la ve el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE negocio_miembro RENAME COLUMN rol TO rol_personal');
        DB::statement('ALTER TABLE negocio_miembro RENAME CONSTRAINT negocio_miembro_rol_check TO negocio_miembro_rol_personal_check');
        DB::statement('ALTER INDEX negocio_miembro_negocio_id_rol_index RENAME TO negocio_miembro_negocio_id_rol_personal_index');

        DB::statement('ALTER TABLE asignacion RENAME COLUMN rol TO rol_personal');
        DB::statement('ALTER TABLE asignacion RENAME CONSTRAINT asignacion_rol_check TO asignacion_rol_personal_check');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE asignacion RENAME CONSTRAINT asignacion_rol_personal_check TO asignacion_rol_check');
        DB::statement('ALTER TABLE asignacion RENAME COLUMN rol_personal TO rol');

        DB::statement('ALTER INDEX negocio_miembro_negocio_id_rol_personal_index RENAME TO negocio_miembro_negocio_id_rol_index');
        DB::statement('ALTER TABLE negocio_miembro RENAME CONSTRAINT negocio_miembro_rol_personal_check TO negocio_miembro_rol_check');
        DB::statement('ALTER TABLE negocio_miembro RENAME COLUMN rol_personal TO rol');
    }
};
