<?php

namespace Tests\Feature\Esquema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Toda factory tiene que producir una fila que la base acepte.
 *
 * El esquema está lleno de `CHECK` y de claves foráneas que cruzan módulos, así
 * que una factory con un valor fuera del enum o una relación mal encadenada no
 * se descubre hasta que alguien escribe un test de negocio y se topa con ella.
 * Aquí se descubre de una vez.
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Los proveedores de datos corren antes de que Laravel arranque: nada de
     * helpers del contenedor aquí.
     *
     * @return array<string,array{class-string<Model>}>
     */
    public static function modelos(): array
    {
        $casos = [];

        foreach (glob(dirname(__DIR__, 3).'/database/factories/*Factory.php') as $archivo) {
            $modelo = 'App\\Models\\'.basename($archivo, 'Factory.php');

            if (is_subclass_of($modelo, Model::class)) {
                $casos[class_basename($modelo)] = [$modelo];
            }
        }

        return $casos;
    }

    #[DataProvider('modelos')]
    public function test_la_factory_produce_una_fila_valida(string $modelo): void
    {
        $tabla = (new $modelo)->getTable();
        $antes = DB::table($tabla)->count();

        $registro = $modelo::factory()->create();

        $this->assertTrue($registro->exists, "{$modelo}::factory() no persistió la fila.");
        $this->assertSame(
            $antes + 1,
            DB::table($tabla)->count(),
            "{$modelo}::factory() no dejó exactamente una fila nueva en '{$tabla}'.",
        );
    }

    #[DataProvider('modelos')]
    public function test_la_factory_puede_crear_varias_filas(string $modelo): void
    {
        $tabla = (new $modelo)->getTable();
        $antes = DB::table($tabla)->count();

        $modelo::factory()->count(3)->create();

        $this->assertSame($antes + 3, DB::table($tabla)->count());
    }
}
