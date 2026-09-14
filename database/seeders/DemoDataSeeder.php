<?php

namespace Database\Seeders;

use App\Models\Asignacion;
use App\Models\CatalogoServicio;
use App\Models\ClientePerfil;
use App\Models\Local;
use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Plan;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use App\Models\Usuario;
use App\Modules\Catalog\Application\ServicioLocalService;
use App\Modules\Directory\Application\HorarioLocalService;
use App\Modules\Directory\Application\LocalService;
use App\Modules\Directory\Application\NegocioService;
use App\Modules\Scheduling\Application\CitaService;
use App\Modules\Scheduling\Application\Transiciones\CancelarCita;
use App\Modules\Scheduling\Application\Transiciones\CompletarCita;
use App\Modules\Scheduling\Application\Transiciones\ConfirmarCita;
use App\Modules\Scheduling\Application\Transiciones\IniciarCita;
use App\Modules\Scheduling\Application\Transiciones\MarcarNoShow;
use App\Modules\Staffing\Application\HabilidadService;
use App\Modules\Staffing\Application\ProfesionalService;
use App\Modules\Staffing\Application\RecursoService;
use App\Modules\Staffing\Application\TurnoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Escenario ficticio completo para desarrollo manual y demos: 3 negocios (uno
 * por vertical activa en la v1), cada uno con local, servicios reales del
 * catálogo maestro, profesionales con turno y habilidades, clientes, y un
 * historial de citas que recorre los estados del §6.
 *
 * No es idempotente a propósito (correrlo dos veces duplica el escenario) y
 * no se encadena en `DatabaseSeeder` — es solo para entornos locales:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * Reusa los `Application/*Service` reales del proyecto en vez de Eloquent
 * directo, para que el escenario nazca con los mismos invariantes que
 * produciría la API (membresía del propietario, constraint de traslape de
 * citas, etc.) — la única excepción son las tablas hoja sin lógica propia
 * (`cliente_perfil`, `negocio_miembro`), donde no existe un servicio que
 * envolverlas justifique.
 */
class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'Fullpinta123!';

    /** @var array<int, array{rol: string, contexto: string, email: string, password: string}> */
    private array $credenciales = [];

    public function run(
        NegocioService $negocios,
        LocalService $locales,
        HorarioLocalService $horarios,
        ServicioLocalService $servicios,
        RecursoService $recursos,
        ProfesionalService $profesionales,
        TurnoService $turnos,
        HabilidadService $habilidades,
        CitaService $citas,
        ConfirmarCita $confirmar,
        IniciarCita $iniciar,
        CompletarCita $completar,
        CancelarCita $cancelar,
        MarcarNoShow $marcarNoShow,
    ): void {
        if (app()->environment('production')) {
            $this->command?->error('DemoDataSeeder no corre en producción — es solo para entornos locales.');

            return;
        }

        // Catálogo maestro (vertical, categorías, catalogo_servicio, tipo_recurso,
        // plan...) del que este escenario depende. Idempotente: si ya corrió,
        // solo actualiza en vez de duplicar.
        $this->call(DatabaseSeeder::class);

        // --- Clientes compartidos entre los tres negocios -------------------
        // `cliente3` absorbe el no-show en las tres historias: si fuera
        // `cliente1`/`cliente2`, al 3er no-show `requiere_confirmacion` se
        // activa (§5.6) y la siguiente cita de ese cliente nace `confirmada`
        // directo en vez de `reservada` — rompería el resto del escenario,
        // que asume que `ConfirmarCita` todavía tiene un hold que confirmar.
        $cliente1 = $this->cliente('María Fernanda Vélez', 'cliente1@fullpinta.test');
        $cliente2 = $this->cliente('Jorge Andrade', 'cliente2@fullpinta.test');
        $cliente3 = $this->cliente('Diana Salazar', 'cliente3@fullpinta.test');

        // --- Negocio 1: Barbería (plan Pro, 2 profesionales) ----------------
        [$negocioBarberia, $duenoBarberia] = $this->negocio(
            $negocios, 'Barbería Estilo Urbano', '0992345678001', 'Kevin Torres',
            'dueno.barberia@fullpinta.test', pro: true,
        );

        $localBarberia = $this->local(
            $locales, $horarios, $negocioBarberia, 'Sucursal Centro',
            'Av. 9 de Octubre y Chile, Guayaquil', -2.1894, -79.8891,
        );

        $servicioCorteClasico = $this->servicioLocal($servicios, $localBarberia, 'barberia', 'Corte clásico', 8.00, 30);
        $servicioCorteFade = $this->servicioLocal($servicios, $localBarberia, 'barberia', 'Corte fade', 10.00, 40);
        $servicioCorteBarba = $this->servicioLocal($servicios, $localBarberia, 'barberia', 'Corte + barba', 14.00, 55);
        $servicioBarba = $this->servicioLocal($servicios, $localBarberia, 'barberia', 'Perfilado de barba', 6.00, 20);

        $this->recurso($recursos, $localBarberia, 'silla', 'Silla 1');
        $this->recurso($recursos, $localBarberia, 'silla', 'Silla 2');

        $juan = $this->profesionalConCuenta(
            $profesionales, $turnos, $habilidades, $localBarberia, 'Juan Pérez',
            'profesional.barberia@fullpinta.test', 'barbero', 50,
            [$servicioCorteClasico, $servicioCorteFade, $servicioCorteBarba, $servicioBarba],
        );
        $this->profesionalSinCuenta(
            $profesionales, $turnos, $habilidades, $localBarberia, 'Carlos Ruiz', 'barbero', 45,
            [$servicioCorteClasico, $servicioCorteFade],
        );

        $this->miembroRecepcion($negocioBarberia, $localBarberia, 'María López', 'recepcion.barberia@fullpinta.test');

        $this->historialDeCitas(
            $citas, $confirmar, $iniciar, $completar, $cancelar, $marcarNoShow,
            $localBarberia, $juan, $duenoBarberia, $cliente1, $cliente2, $cliente3,
            [$servicioCorteClasico, $servicioCorteFade],
        );
        $this->walkIn($citas, $iniciar, $completar, $localBarberia, $juan, $servicioCorteClasico);

        // --- Negocio 2: Estética (plan Free, 1 profesional) -----------------
        [$negocioEstetica, $duenoEstetica] = $this->negocio(
            $negocios, 'Salón Bella Vita', '0992345678002', 'Ana Gómez',
            'dueno.estetica@fullpinta.test', pro: false,
        );

        $localEstetica = $this->local(
            $locales, $horarios, $negocioEstetica, 'Bella Vita - Urdesa',
            'Circunvalación Sur y Laureles, Guayaquil', -2.1560, -79.9080,
        );

        $servicioCorteDama = $this->servicioLocal($servicios, $localEstetica, 'estetica', 'Corte de dama', 12.00, 45);
        $servicioCepillado = $this->servicioLocal($servicios, $localEstetica, 'estetica', 'Cepillado', 8.00, 40);
        $servicioTinte = $this->servicioLocal($servicios, $localEstetica, 'estetica', 'Tinte', 35.00, 90);

        $this->recurso($recursos, $localEstetica, 'silla', 'Silla 1');
        $this->recurso($recursos, $localEstetica, 'lavacabezas', 'Lavacabezas 1');

        $estefania = $this->profesionalConCuenta(
            $profesionales, $turnos, $habilidades, $localEstetica, 'Estefanía Vera',
            'profesional.estetica@fullpinta.test', 'estilista', 45,
            [$servicioCorteDama, $servicioCepillado, $servicioTinte],
        );

        $this->historialDeCitas(
            $citas, $confirmar, $iniciar, $completar, $cancelar, $marcarNoShow,
            $localEstetica, $estefania, $duenoEstetica, $cliente1, $cliente2, $cliente3,
            [$servicioCorteDama, $servicioCepillado],
        );

        // --- Negocio 3: Uñas (plan Free, 1 profesional) ---------------------
        [$negocioUnas, $duenoUnas] = $this->negocio(
            $negocios, 'Uñas & Detalles', '0992345678003', 'Priscila Chávez',
            'dueno.unas@fullpinta.test', pro: false,
        );

        $localUnas = $this->local(
            $locales, $horarios, $negocioUnas, 'Uñas & Detalles - Alborada',
            'Av. Rodolfo Baquerizo Nazur, Guayaquil', -2.1420, -79.9010,
        );

        $servicioManicura = $this->servicioLocal($servicios, $localUnas, 'unas', 'Manicura', 12.00, 40);
        $servicioPedicura = $this->servicioLocal($servicios, $localUnas, 'unas', 'Pedicura', 15.00, 50);
        $servicioEsmaltado = $this->servicioLocal($servicios, $localUnas, 'unas', 'Esmaltado semipermanente', 18.00, 60);

        $this->recurso($recursos, $localUnas, 'mesa_unas', 'Mesa 1');
        $this->recurso($recursos, $localUnas, 'mesa_unas', 'Mesa 2');

        $gabriela = $this->profesionalConCuenta(
            $profesionales, $turnos, $habilidades, $localUnas, 'Gabriela Soto',
            'profesional.unas@fullpinta.test', 'manicurista', 50,
            [$servicioManicura, $servicioPedicura, $servicioEsmaltado],
        );

        $this->historialDeCitas(
            $citas, $confirmar, $iniciar, $completar, $cancelar, $marcarNoShow,
            $localUnas, $gabriela, $duenoUnas, $cliente1, $cliente2, $cliente3,
            [$servicioManicura, $servicioPedicura],
        );

        $this->mostrarCredenciales();
    }

    // -------------------------------------------------------------------
    // Negocio / Local / Catálogo
    // -------------------------------------------------------------------

    /** @return array{0: Negocio, 1: Usuario} */
    private function negocio(
        NegocioService $servicio, string $nombreMarca, string $ruc,
        string $nombreDueno, string $email, bool $pro,
    ): array {
        $dueno = $this->usuarioConCuenta($nombreDueno, $email);

        $negocio = $servicio->crear($dueno, $nombreMarca, $ruc);

        if ($pro) {
            $negocio->update([
                'plan_id' => Plan::where('codigo', 'pro')->value('id'),
                'plan_vigente_hasta' => now()->addMonth()->toDateString(),
            ]);
        }

        $this->credenciales[] = [
            'rol' => 'Propietario',
            'contexto' => $nombreMarca,
            'email' => $email,
            'password' => self::PASSWORD,
        ];

        return [$negocio, $dueno];
    }

    private function local(
        LocalService $servicio, HorarioLocalService $horarios, Negocio $negocio,
        string $nombre, string $direccion, float $lat, float $lng,
    ): Local {
        $local = $servicio->crear($negocio, [
            'nombre' => $nombre,
            'direccion' => $direccion,
            'lat' => $lat,
            'lng' => $lng,
            'telefono' => '04'.fake()->numerify('#######'),
            'whatsapp' => '09'.fake()->numerify('########'),
        ]);

        $servicio->activar($local);

        // Lunes(1) a sábado(6) — domingo(0) cerrado, como cualquier barbería.
        foreach (range(1, 6) as $diaSemana) {
            $horarios->crear($local, ['dia_semana' => $diaSemana, 'abre' => '09:00', 'cierra' => '19:00']);
        }

        return $local->fresh();
    }

    private function servicioLocal(
        ServicioLocalService $servicio, Local $local, string $vertical,
        string $nombreCatalogo, float $precio, int $duracion,
    ): ServicioLocal {
        $catalogoServicioId = CatalogoServicio::query()
            ->whereHas('categoria.vertical', fn ($q) => $q->where('codigo', $vertical))
            ->where('nombre', $nombreCatalogo)
            ->value('id');

        return $servicio->crear($local, [
            'catalogo_servicio_id' => $catalogoServicioId,
            'precio' => $precio,
            'duracion_min' => $duracion,
        ]);
    }

    private function recurso(RecursoService $servicio, Local $local, string $tipo, string $nombre): void
    {
        $servicio->crear($local, ['tipo' => $tipo, 'nombre' => $nombre]);
    }

    // -------------------------------------------------------------------
    // Personas
    // -------------------------------------------------------------------

    private function usuarioConCuenta(string $nombre, string $email): Usuario
    {
        return Usuario::factory()->create([
            'nombre' => $nombre,
            'email' => $email,
            'password_hash' => Hash::make(self::PASSWORD),
        ]);
    }

    private function cliente(string $nombre, string $email): Usuario
    {
        $cliente = $this->usuarioConCuenta($nombre, $email);

        ClientePerfil::create([
            'usuario_id' => $cliente->id,
            'no_shows' => 0,
            'cancelaciones_tardias' => 0,
            'requiere_confirmacion' => false,
        ]);

        $this->credenciales[] = [
            'rol' => 'Cliente',
            'contexto' => '— (agenda para sí mismo en cualquier local)',
            'email' => $email,
            'password' => self::PASSWORD,
        ];

        return $cliente;
    }

    private function miembroRecepcion(Negocio $negocio, Local $local, string $nombre, string $email): void
    {
        $usuario = $this->usuarioConCuenta($nombre, $email);

        NegocioMiembro::create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $negocio->id,
            'local_id' => $local->id,
            'rol' => 'recepcion',
            'desde' => now()->toDateString(),
        ]);

        $this->credenciales[] = [
            'rol' => 'Recepción',
            'contexto' => $local->nombre,
            'email' => $email,
            'password' => self::PASSWORD,
        ];
    }

    /** @param  array<int, ServicioLocal>  $conHabilidad */
    private function profesionalConCuenta(
        ProfesionalService $profesionales, TurnoService $turnos, HabilidadService $habilidades,
        Local $local, string $nombre, string $email, string $rol, float $comisionPct,
        array $conHabilidad,
    ): Profesional {
        $usuario = $this->usuarioConCuenta($nombre, $email);

        $profesional = $profesionales->crearConAsignacion(
            $local,
            ['usuario_id' => $usuario->id, 'nombre' => $nombre],
            ['rol' => $rol, 'modalidad' => 'empleado', 'comision_pct' => $comisionPct],
        );

        $this->turnoYHabilidades($turnos, $habilidades, $profesional, $local, $conHabilidad);

        $this->credenciales[] = [
            'rol' => 'Profesional',
            'contexto' => $local->nombre,
            'email' => $email,
            'password' => self::PASSWORD,
        ];

        return $profesional;
    }

    /** @param  array<int, ServicioLocal>  $conHabilidad */
    private function profesionalSinCuenta(
        ProfesionalService $profesionales, TurnoService $turnos, HabilidadService $habilidades,
        Local $local, string $nombre, string $rol, float $comisionPct, array $conHabilidad,
    ): Profesional {
        $profesional = $profesionales->crearConAsignacion(
            $local,
            ['nombre' => $nombre],
            ['rol' => $rol, 'modalidad' => 'empleado', 'comision_pct' => $comisionPct],
        );

        $this->turnoYHabilidades($turnos, $habilidades, $profesional, $local, $conHabilidad);

        return $profesional;
    }

    /** @param  array<int, ServicioLocal>  $conHabilidad */
    private function turnoYHabilidades(
        TurnoService $turnos, HabilidadService $habilidades,
        Profesional $profesional, Local $local, array $conHabilidad,
    ): void {
        $asignacion = Asignacion::where('local_id', $local->id)
            ->where('profesional_id', $profesional->id)
            ->firstOrFail();

        foreach (range(1, 6) as $diaSemana) {
            $turnos->crear($asignacion, [
                'dia_semana' => $diaSemana,
                'entra' => '09:00',
                'sale' => '18:00',
                'vigente_desde' => now()->subMonth()->toDateString(),
            ]);
        }

        foreach ($conHabilidad as $servicioLocal) {
            $habilidades->crear($profesional, ['servicio_local_id' => $servicioLocal->id]);
        }
    }

    // -------------------------------------------------------------------
    // Citas: recorren los estados del §6 para que el escenario no sea solo
    // "todo confirmado" — así se puede probar reseñas (solo completadas),
    // no-show y cancelación desde el día uno.
    // -------------------------------------------------------------------

    /** @param  array<int, ServicioLocal>  $serviciosDisponibles */
    private function historialDeCitas(
        CitaService $citas, ConfirmarCita $confirmar, IniciarCita $iniciar,
        CompletarCita $completar, CancelarCita $cancelar, MarcarNoShow $marcarNoShow,
        Local $local, Profesional $profesional, Usuario $dueno,
        Usuario $cliente1, Usuario $cliente2, Usuario $cliente3, array $serviciosDisponibles,
    ): void {
        [$servicioA, $servicioB] = $serviciosDisponibles;

        // Completada hace una semana: habilita reseña y cuenta para ranking.
        $completada1 = $citas->reservar($local, $cliente1, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicioA->id],
            'inicio' => now()->subDays(7)->setTime(10, 0)->toDateTimeString(),
        ]);
        $confirmar($completada1, $cliente1);
        $iniciar($completada1, $dueno);
        $completar($completada1, $dueno, propina: 2.00);

        // Completada hace 2 días, con el segundo cliente.
        $completada2 = $citas->reservar($local, $cliente2, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicioB->id],
            'inicio' => now()->subDays(2)->setTime(11, 0)->toDateTimeString(),
        ]);
        $confirmar($completada2, $cliente2);
        $iniciar($completada2, $dueno);
        $completar($completada2, $dueno);

        // No-show hace 5 días: el local la marca cuando el cliente no llega.
        // `cliente3`, no `cliente1`/`cliente2` — ver comentario en `run()`.
        $noShow = $citas->reservar($local, $cliente3, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicioA->id],
            'inicio' => now()->subDays(5)->setTime(9, 0)->toDateTimeString(),
        ]);
        $confirmar($noShow, $cliente3);
        $marcarNoShow($noShow, $dueno);

        // Cancelada por el cliente hace 3 días.
        $cancelada = $citas->reservar($local, $cliente2, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicioB->id],
            'inicio' => now()->subDays(3)->setTime(16, 0)->toDateTimeString(),
        ]);
        $confirmar($cancelada, $cliente2);
        $cancelar($cancelada, $cliente2, motivo: 'Se le complicó la hora.');

        // Confirmada para mañana: para probar la agenda del local hoy mismo.
        $confirmada = $citas->reservar($local, $cliente1, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicioA->id],
            'inicio' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
        ]);
        $confirmar($confirmada, $cliente1);

        // Hold sin confirmar en 3 días: así se puede probar la expiración.
        $citas->reservar($local, $cliente2, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicioB->id],
            'inicio' => now()->addDays(3)->setTime(14, 0)->toDateTimeString(),
        ]);
    }

    /** Walk-in de mostrador (§4.7): cliente sombra, sin cuenta, ocupa slot igual. */
    private function walkIn(
        CitaService $citas, IniciarCita $iniciar, CompletarCita $completar,
        Local $local, Profesional $profesional, ServicioLocal $servicio,
    ): void {
        $walkIn = $citas->registrarWalkIn($local, [
            'profesional_id' => $profesional->id,
            'servicios' => [$servicio->id],
            'inicio' => now()->setTime(15, 0)->toDateTimeString(),
            'telefono' => '0990001122',
            'nombre' => 'Cliente Mostrador',
        ]);

        $iniciar($walkIn, null);
        $completar($walkIn, null);
    }

    private function mostrarCredenciales(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Escenario de demo listo. Credenciales (login: POST /api/v1/auth/login):');
        $this->command->table(
            ['Rol', 'Contexto', 'Email', 'Password'],
            array_map(fn ($c) => [$c['rol'], $c['contexto'], $c['email'], $c['password']], $this->credenciales),
        );
    }
}
