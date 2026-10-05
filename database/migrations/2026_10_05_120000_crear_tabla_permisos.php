<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos declarados por rol (§3.2): los cinco roles de la matriz —
 * cliente, profesional, recepción, propietario y admin (el 5to valor que la
 * matriz no cubre, ver docblock de `ContextoAcceso`) — y qué puede hacer cada
 * uno. Antes esto vivía escrito a mano en `ContextoAcceso` (`in_array([...])`)
 * y en comparaciones de propiedad repartidas en cada Policy; ahora la mayoría
 * se consulta aquí, así que cambiar quién puede ver comisiones o responder
 * reseñas es un `INSERT`/`DELETE` en `rol_permiso`, no un despliegue.
 *
 * ## Lo que esta tabla deliberadamente NO cubre
 *
 * La gestión de suscripción y facturación es del dueño LEGAL
 * (`negocio.propietario_id`), ni siquiera de quien tiene `rol = 'propietario'`
 * en `negocio_miembro` si llegaran a divergir — por eso, aunque existe la fila
 * `gestionar_suscripcion` en `permiso` (documentación/auditoría), la Policy
 * sigue comparando `negocio.propietario_id` directo, no esta tabla.
 *
 * Esta tabla tampoco reemplaza el check de "¿es este registro ESPECÍFICAMENTE
 * suyo?" (es esta SU cita, es este profesional él mismo) — eso sigue en cada
 * Policy del recurso. Lo que sí resuelve es "¿tiene este ROL, en general,
 * permiso para esta acción?" — la otra mitad de la pregunta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();   // cliente, profesional, recepcion, propietario, admin
            $table->string('nombre');
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        Schema::create('permiso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 60)->unique();   // ver_interno, administrar_negocio, ...
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignUuid('rol_id')->constrained('rol')->cascadeOnDelete();
            $table->foreignUuid('permiso_id')->constrained('permiso')->cascadeOnDelete();

            $table->primary(['rol_id', 'permiso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permiso');
        Schema::dropIfExists('rol');
    }
};
