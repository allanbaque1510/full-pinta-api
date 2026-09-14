<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens de Sanctum.
 *
 * Dos cambios sobre la migración que publica el paquete:
 *
 * 1. `uuidMorphs` en vez de `morphs`: las claves primarias del dominio son uuid
 *    (§4.2), y el `bigint` por defecto no puede apuntar a `usuario.id`.
 * 2. Timestamps con zona horaria, como todo lo demás. Todo en UTC en base.
 *
 * La PK propia de la tabla sigue siendo `bigint` autoincremental: es del
 * paquete, no del dominio, y cambiarla es pelear con Sanctum sin ganar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
