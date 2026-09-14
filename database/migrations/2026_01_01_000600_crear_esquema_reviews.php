<?php

use App\Support\Esquema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Reviews (§4.8): reseñas y reportes de moderación.
 *
 * Reglas duras que NO están aquí porque son de aplicación: solo se puede
 * reseñar una cita `completada`, y la ventana es de 14 días desde la cita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resena', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // UNIQUE: una reseña por cita. Sin esto, el barbero se autoreseña y
            // el ranking no vale nada.
            $table->foreignUuid('cita_id')->unique()->constrained('cita')->cascadeOnDelete();

            // Desnormalizados para las consultas de ranking y de perfil.
            $table->foreignUuid('local_id')->constrained('local')->cascadeOnDelete();
            $table->foreignUuid('profesional_id')->constrained('profesional')->cascadeOnDelete();

            $table->smallInteger('puntaje_local');
            $table->smallInteger('puntaje_profesional')->nullable();
            $table->smallInteger('puntualidad')->nullable();
            $table->smallInteger('limpieza')->nullable();
            $table->text('comentario')->nullable();
            $table->string('estado', 20)->default('publicada');
            $table->text('respuesta_local')->nullable();
            $table->timestampTz('respuesta_at')->nullable();
            $table->timestampsTz();

            $table->index(['local_id', 'estado']);
            $table->index(['profesional_id', 'estado']);
        });

        Esquema::enum('resena', 'estado', ['publicada', 'en_revision', 'oculta']);
        Esquema::check('resena', 'puntaje_local', 'puntaje_local BETWEEN 1 AND 5');
        Esquema::check('resena', 'puntaje_profesional',
            'puntaje_profesional IS NULL OR puntaje_profesional BETWEEN 1 AND 5');
        Esquema::check('resena', 'puntualidad', 'puntualidad IS NULL OR puntualidad BETWEEN 1 AND 5');
        Esquema::check('resena', 'limpieza', 'limpieza IS NULL OR limpieza BETWEEN 1 AND 5');

        // `tipo` + `objeto_id` es polimórfico a propósito: se reporta cualquier
        // cosa. Por eso no lleva FK.
        Schema::create('reporte', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipo', 20);
            $table->uuid('objeto_id');
            $table->foreignUuid('reportante_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('motivo', 30);
            $table->text('detalle')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->timestampsTz();

            $table->index(['estado', 'created_at']);
            $table->index(['tipo', 'objeto_id']);
        });

        Esquema::enum('reporte', 'tipo', ['resena', 'foto', 'local', 'profesional']);
        Esquema::enum('reporte', 'motivo', [
            'difamacion', 'contenido_inapropiado', 'falso', 'spam', 'otro',
        ]);
        Esquema::enum('reporte', 'estado', ['pendiente', 'resuelto', 'descartado']);
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte');
        Schema::dropIfExists('resena');
    }
};
