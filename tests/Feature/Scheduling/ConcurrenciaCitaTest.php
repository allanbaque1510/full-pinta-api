<?php

namespace Tests\Feature\Scheduling;

use App\Models\Asignacion;
use App\Models\CatalogoServicio;
use App\Models\Habilidad;
use App\Models\HorarioLocal;
use App\Models\Local;
use App\Models\Negocio;
use App\Models\Profesional;
use App\Models\ServicioCategoria;
use App\Models\ServicioLocal;
use App\Models\TipoRecurso;
use App\Models\Turno;
use App\Models\Usuario;
use App\Models\Vertical;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * §5.7: "20 peticiones paralelas al mismo slot, gana exactamente una" — el
 * único test que no se puede simular con mocks, tiene que pegarle a
 * Postgres de verdad bajo concurrencia real (skill `disponibilidad`).
 *
 * Sin `RefreshDatabase` A PROPÓSITO: ese trait envuelve el test en una
 * transacción que nunca se confirma (se hace rollback al final, por
 * velocidad). Los procesos `php -S` hijos abren su PROPIA conexión a
 * Postgres — no pueden ver filas de una transacción ajena todavía sin
 * confirmar. Por eso los datos se crean con COMMIT real y se limpian a mano
 * en `tearDown()`, en el orden que las FK exigen.
 *
 * El servidor embebido de PHP es de UN SOLO worker en este entorno: el modo
 * multi-worker usa `fork()`, que no existe fuera de POSIX — en
 * Windows/Laragon queda en 1 worker aunque se fije `PHP_CLI_SERVER_WORKERS`.
 * Un solo proceso serializaría las 20 peticiones y el test dejaría de
 * probar concurrencia real. Por eso se levantan varios procesos `php -S`
 * INDEPENDIENTES (procesos de sistema operativo reales, no hilos) en
 * puertos distintos, todos contra la misma base de datos, y las 20
 * peticiones se reparten entre ellos con curl_multi: así sí puede haber dos
 * `INSERT` corriendo al mismo tiempo de verdad, y es el constraint
 * `EXCLUDE` de Postgres el que decide, no PHP.
 */
class ConcurrenciaCitaTest extends TestCase
{
    private const SERVIDORES = 5;

    private const PETICIONES = 20;

    /** @var list<resource> */
    private array $procesos = [];

    /** @var list<int> */
    private array $puertos = [];

    /** @var (callable():void)|null */
    private $limpieza = null;

    protected function setUp(): void
    {
        parent::setUp();

        $router = base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');

        for ($i = 0; $i < self::SERVIDORES; $i++) {
            $puerto = $this->puertoLibre();
            $comando = [PHP_BINARY, '-S', "127.0.0.1:{$puerto}", $router];
            $descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

            // Sin override de entorno: el hijo hereda el mismo entorno que ya
            // tiene este proceso de PHPUnit — incluidas las variables de
            // `phpunit.xml` (DB_DATABASE=fullpinta_test, etc.), puestas ahí
            // vía `putenv()`.
            $proceso = proc_open($comando, $descriptores, $pipes, base_path('public'), null);

            if (! is_resource($proceso)) {
                $this->fail("No se pudo levantar el servidor de prueba en el puerto {$puerto}.");
            }

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $this->procesos[] = $proceso;
            $this->puertos[] = $puerto;
        }

        foreach ($this->puertos as $puerto) {
            $this->esperarServidor($puerto);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->procesos as $proceso) {
            if (is_resource($proceso)) {
                proc_terminate($proceso);
                proc_close($proceso);
            }
        }

        if ($this->limpieza !== null) {
            ($this->limpieza)();
        }

        parent::tearDown();
    }

    public function test_veinte_peticiones_paralelas_al_mismo_slot_gana_exactamente_una(): void
    {
        [$local, $profesional, $servicioLocal, $token] = $this->prepararEscenario();

        $inicio = CarbonImmutable::now()->next(1)->setTime(10, 0)->toIso8601String();

        $cuerpo = json_encode([
            'profesional_id' => $profesional->id,
            'servicios' => [$servicioLocal->id],
            'inicio' => $inicio,
        ]);

        $multi = curl_multi_init();
        $handles = [];

        for ($i = 0; $i < self::PETICIONES; $i++) {
            $puerto = $this->puertos[$i % count($this->puertos)];
            $url = "http://127.0.0.1:{$puerto}/api/v1/locales/{$local->id}/citas";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $cuerpo,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "Authorization: Bearer {$token}",
                    // Claves distintas: son 20 CLIENTES distintos peleando por
                    // el mismo slot, no el mismo cliente reintentando la misma
                    // petición — si compartieran clave, el middleware de
                    // idempotencia las deduplicaría antes de llegar al
                    // constraint, y dejaría de probar la concurrencia real.
                    'Idempotency-Key: '.Str::uuid()->toString(),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
            ]);

            curl_multi_add_handle($multi, $ch);
            $handles[] = $ch;
        }

        do {
            $estado = curl_multi_exec($multi, $activos);

            if ($activos) {
                curl_multi_select($multi);
            }
        } while ($activos > 0 && $estado === CURLM_OK);

        $codigos = array_map(fn ($ch) => curl_getinfo($ch, CURLINFO_HTTP_CODE), $handles);
        $errores = array_map(fn ($ch) => curl_error($ch), $handles);
        $cuerpos = array_map(fn ($ch) => curl_multi_getcontent($ch), $handles);

        foreach ($handles as $ch) {
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        $exitosos = count(array_filter($codigos, fn ($c) => $c === 201));
        $conflictos = count(array_filter($codigos, fn ($c) => $c === 409));

        $this->assertSame(
            1,
            $exitosos,
            'Exactamente una de las 20 peticiones concurrentes debe ganar el slot. '
            .'Códigos: '.implode(',', $codigos)
            .' Errores curl: '.implode('|', array_filter($errores))
            .' Cuerpos: '.implode('|', array_slice($cuerpos, 0, 3)),
        );
        $this->assertSame(self::PETICIONES - 1, $conflictos);
        $this->assertSame(1, DB::table('cita')->count());
    }

    /** @return array{0:Local,1:Profesional,2:ServicioLocal,3:string} */
    private function prepararEscenario(): array
    {
        $vertical = Vertical::factory()->create();
        $categoria = ServicioCategoria::factory()->create(['vertical_id' => $vertical->id]);
        $tipoNinguno = TipoRecurso::where('codigo', 'ninguno')->first()
            ?? TipoRecurso::factory()->create(['codigo' => 'ninguno', 'nombre' => 'Ninguno']);

        // `CatalogoServicio::factory()->create()` NO sirve aquí: su
        // `definition()` resuelve/crea un `TipoRecurso` "silla" por defecto
        // como efecto colateral ANTES de que el `tipo_recurso_id` que se le
        // pasa lo pise — inofensivo bajo `RefreshDatabase` (se revierte con
        // todo lo demás), pero este test no usa esa transacción, así que ese
        // efecto colateral quedaría comprometido en la base para siempre.
        // Se arma la fila directo, sin pasar por la factory.
        $catalogo = CatalogoServicio::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Servicio de prueba',
            'slug' => 'servicio-prueba-'.Str::random(8),
            'duracion_base_min' => 30,
            'tipo_recurso_id' => $tipoNinguno->id,
            'activo' => true,
        ]);

        $local = Local::factory()->sinLeadTime()->create();
        // `Local::factory()` crea un `Negocio` por defecto si no se le pasa
        // `negocio_id`, y `Negocio::factory()` a su vez crea un `Usuario`
        // dueño — cadena implícita que también hay que limpiar (§ más abajo).
        $negocio = Negocio::with('propietario')->findOrFail($local->negocio_id);
        HorarioLocal::factory()->create(['local_id' => $local->id, 'dia_semana' => 1, 'abre' => '09:00', 'cierra' => '19:00']);

        $profesional = Profesional::factory()->create();
        $asignacion = Asignacion::factory()->create(['local_id' => $local->id, 'profesional_id' => $profesional->id]);
        Turno::factory()->para($asignacion)->horario('09:00', '19:00', 1)->create();

        $servicioLocal = ServicioLocal::factory()->create([
            'local_id' => $local->id, 'catalogo_servicio_id' => $catalogo->id,
            'duracion_min' => 30, 'buffer_min' => 0,
        ]);
        Habilidad::factory()->create(['profesional_id' => $profesional->id, 'servicio_local_id' => $servicioLocal->id]);

        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->limpieza = function () use ($local, $negocio, $catalogo, $categoria, $vertical, $profesional, $usuario) {
            DB::table('cita')->where('local_id', $local->id)->delete();
            // Cascada: horario_local, asignacion (y su turno), servicio_local, recurso.
            $local->delete();
            // `local.negocio_id` es RESTRICT: hay que borrar el local antes
            // de poder borrar el negocio (y, con él, a su dueño).
            $negocio->delete();
            $negocio->propietario->delete();
            $catalogo->delete();
            $categoria->delete();
            $vertical->delete();
            $profesional->delete();
            $usuario->tokens()->delete();
            $usuario->delete();
        };

        return [$local, $profesional, $servicioLocal, $token];
    }

    private function puertoLibre(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $nombre = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($nombre, strrpos($nombre, ':') + 1);
    }

    private function esperarServidor(int $puerto, float $timeoutSegundos = 10.0): void
    {
        $limite = microtime(true) + $timeoutSegundos;

        while (microtime(true) < $limite) {
            $conexion = @fsockopen('127.0.0.1', $puerto, $errno, $errstr, 0.2);

            if ($conexion !== false) {
                fclose($conexion);

                return;
            }

            usleep(50_000);
        }

        $this->fail("El servidor de prueba en el puerto {$puerto} no respondió a tiempo.");
    }
}
