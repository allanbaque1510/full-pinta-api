<?php

namespace Tests\Feature\Scheduling;

use App\Models\Asignacion;
use App\Models\Habilidad;
use App\Models\HorarioLocal;
use App\Models\Local;
use App\Models\Mascota;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use App\Models\TamanoMascota;
use App\Models\TipoRecurso;
use App\Models\Turno;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\CitaService;
use App\Modules\Scheduling\Application\DisponibilidadService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revisión de base de datos 2026-09-28 (pregunta 40): `CitaService` congelaba
 * siempre el precio/duración planos de `servicio_local`, ignorando
 * `servicio_local_tamano` — bañar un yorkshire costaba y duraba lo mismo que
 * un golden. Bloqueador funcional para el rubro mascotas (§16.6).
 */
class PrecioPorTamanoMascotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_cita_item_usa_el_precio_y_duracion_del_tamano_de_la_mascota(): void
    {
        [$local, $profesional, $servicio, $pequeno, $grande] = $this->prepararEscenario();

        $clienteChico = Usuario::factory()->create();
        $mascotaChica = Mascota::factory()->create(['usuario_id' => $clienteChico->id, 'tamano_id' => $pequeno->id]);

        $clienteGrande = Usuario::factory()->create();
        $mascotaGrande = Mascota::factory()->create(['usuario_id' => $clienteGrande->id, 'tamano_id' => $grande->id]);

        $fecha = CarbonImmutable::now()->next(2)->setTime(9, 0); // martes

        $citaChica = app(CitaService::class)->reservar($local, $clienteChico, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicio->id],
            'inicio' => $fecha->toIso8601String(),
            'mascota_id' => $mascotaChica->id,
        ]);

        $citaGrande = app(CitaService::class)->reservar($local, $clienteGrande, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicio->id],
            'inicio' => $fecha->addMinutes(30)->toIso8601String(),
            'mascota_id' => $mascotaGrande->id,
        ]);

        $itemChico = $citaChica->items->first();
        $itemGrande = $citaGrande->items->first();

        $this->assertSame('15.00', (string) $itemChico->precio);
        $this->assertSame(30, $itemChico->duracion_min);
        $this->assertSame('40.00', (string) $itemGrande->precio);
        $this->assertSame(60, $itemGrande->duracion_min);

        $this->assertSame('15.00', (string) $citaChica->precio_total);
        $this->assertSame('40.00', (string) $citaGrande->precio_total);

        // La cita del grande dura más: la ventana [inicio, fin) tiene que
        // reflejar los 60 min del tamaño, no los 20 planos de `servicio_local`.
        $this->assertSame(60.0, $citaGrande->inicio->diffInMinutes($citaGrande->fin));
    }

    public function test_sin_mascota_usa_el_precio_y_duracion_planos_del_servicio(): void
    {
        [$local, $profesional, $servicio] = $this->prepararEscenario();
        $cliente = Usuario::factory()->create();

        $cita = app(CitaService::class)->reservar($local, $cliente, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicio->id],
            'inicio' => CarbonImmutable::now()->next(2)->setTime(9, 0)->toIso8601String(),
        ]);

        $this->assertSame('20.00', (string) $cita->items->first()->precio);
        $this->assertSame(20, $cita->items->first()->duracion_min);
    }

    public function test_la_disponibilidad_calcula_la_ventana_segun_el_tamano_de_la_mascota(): void
    {
        [$local, , $servicio, , $grande] = $this->prepararEscenario();
        $mascota = Mascota::factory()->create(['tamano_id' => $grande->id]);
        $fecha = CarbonImmutable::now()->next(2);

        $slotsSinMascota = app(DisponibilidadService::class)->slots($local, $fecha, [$servicio->id]);
        $slotsConMascota = app(DisponibilidadService::class)->slots($local, $fecha, [$servicio->id], mascotaId: $mascota->id);

        $this->assertSame(20.0, $slotsSinMascota->first()->inicio->diffInMinutes($slotsSinMascota->first()->fin));
        $this->assertSame(60.0, $slotsConMascota->first()->inicio->diffInMinutes($slotsConMascota->first()->fin));
    }

    /**
     * @return array{0:Local,1:Profesional,2:ServicioLocal,3:TamanoMascota,4:TamanoMascota}
     */
    private function prepararEscenario(): array
    {
        $local = Local::factory()->sinLeadTime()->create();
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 2, 'abre' => '09:00', 'cierra' => '13:00']);

        $profesional = Profesional::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asignacion)->horario('09:00', '13:00', 2)->create();

        $tipoNinguno = TipoRecurso::where('codigo', 'ninguno')->first()
            ?? TipoRecurso::factory()->create(['codigo' => 'ninguno', 'nombre' => 'Ninguno']);

        // Plano: lo que se cobra sin mascota (o si el tamaño no tiene fila propia).
        $servicio = ServicioLocal::factory()->create([
            'local_id' => $local->id, 'precio' => 20, 'duracion_min' => 20, 'buffer_min' => 0,
        ]);
        $servicio->catalogoServicio()->update(['tipo_recurso_id' => $tipoNinguno->id]);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicio->id]);

        $pequeno = TamanoMascota::factory()->create(['codigo' => 'pequeno']);
        $grande = TamanoMascota::factory()->create(['codigo' => 'grande']);

        $servicio->tamanos()->create(['tamano_id' => $pequeno->id, 'precio' => 15, 'duracion_min' => 30]);
        $servicio->tamanos()->create(['tamano_id' => $grande->id, 'precio' => 40, 'duracion_min' => 60]);

        return [$local, $profesional, $servicio, $pequeno, $grande];
    }
}
