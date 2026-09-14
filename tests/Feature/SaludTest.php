<?php

namespace Tests\Feature;

use Tests\TestCase;

class SaludTest extends TestCase
{
    public function test_la_raiz_de_la_api_reporta_el_servicio_operativo(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonPath('estado', 'operativo')
            ->assertJsonPath('version', 'v1');
    }

    /**
     * El motor de disponibilidad depende de PostGIS, btree_gist y el tipo
     * `franja`. Si alguno falta, media especificación es inimplementable — así
     * que falla aquí y no a mitad de una migración.
     */
    public function test_las_dependencias_de_postgres_estan_disponibles(): void
    {
        $this->getJson('/api/v1')
            ->assertJsonPath('dependencias.base_datos', 'ok')
            ->assertJsonPath('dependencias.postgis', 'ok')
            ->assertJsonPath('dependencias.btree_gist', 'ok')
            ->assertJsonPath('dependencias.tipo_franja', 'ok');
    }

    public function test_el_health_check_de_laravel_responde(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_una_ruta_protegida_responde_401_y_no_redirige(): void
    {
        $this->postJson('/api/v1/user')->assertStatus(405);
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_una_ruta_inexistente_responde_json_y_no_html(): void
    {
        $this->getJson('/api/v1/no-existe')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }
}
