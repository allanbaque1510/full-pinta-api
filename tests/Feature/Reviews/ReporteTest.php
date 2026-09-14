<?php

namespace Tests\Feature\Reviews;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_autenticado_puede_reportar_contenido(): void
    {
        $usuario = Usuario::factory()->create();
        $objetoId = (string) Str::uuid();

        $this->withHeader('Authorization', "Bearer {$usuario->createToken('t')->plainTextToken}")
            ->postJson('/api/v1/reportes', [
                'tipo' => 'resena', 'objeto_id' => $objetoId, 'motivo' => 'spam', 'detalle' => 'Comentario repetido',
            ])
            ->assertCreated()
            ->assertJsonPath('tipo', 'resena')
            ->assertJsonPath('objeto_id', $objetoId)
            ->assertJsonPath('reportante_id', $usuario->id)
            ->assertJsonPath('estado', 'pendiente');
    }

    public function test_rechaza_un_tipo_fuera_del_enum(): void
    {
        $usuario = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$usuario->createToken('t')->plainTextToken}")
            ->postJson('/api/v1/reportes', [
                'tipo' => 'otro_tipo_invalido', 'objeto_id' => (string) Str::uuid(), 'motivo' => 'spam',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }

    public function test_rechaza_un_motivo_fuera_del_enum(): void
    {
        $usuario = Usuario::factory()->create();

        $this->withHeader('Authorization', "Bearer {$usuario->createToken('t')->plainTextToken}")
            ->postJson('/api/v1/reportes', [
                'tipo' => 'local', 'objeto_id' => (string) Str::uuid(), 'motivo' => 'motivo_invalido',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo');
    }
}
