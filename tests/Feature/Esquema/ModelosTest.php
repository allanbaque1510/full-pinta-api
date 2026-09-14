<?php

namespace Tests\Feature\Esquema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Verifica que los 41 modelos concuerden con el esquema real.
 *
 * No prueba reglas de negocio: prueba que ningún modelo apunte a una tabla o a
 * una columna que no existe. Con 43 tablas escritas a mano, un `profesional_id`
 * mal tecleado no se nota hasta que alguien abre esa pantalla en producción.
 */
class ModelosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Los proveedores de datos corren ANTES de que Laravel arranque, así que
     * aquí no se puede usar `app_path()` ni ningún helper del contenedor.
     *
     * @return array<int,array{class-string<Model>}>
     */
    public static function modelos(): array
    {
        $clases = [];

        foreach (glob(dirname(__DIR__, 3).'/app/Models/*.php') as $archivo) {
            $clase = 'App\\Models\\'.basename($archivo, '.php');

            if (is_subclass_of($clase, Model::class)) {
                $clases[] = [$clase];
            }
        }

        return $clases;
    }

    #[DataProvider('modelos')]
    public function test_la_tabla_del_modelo_existe(string $clase): void
    {
        $modelo = new $clase;

        $this->assertTrue(
            Schema::hasTable($modelo->getTable()),
            "El modelo {$clase} apunta a la tabla inexistente '{$modelo->getTable()}'.",
        );
    }

    #[DataProvider('modelos')]
    public function test_las_relaciones_apuntan_a_columnas_que_existen(string $clase): void
    {
        $modelo = new $clase;
        $relaciones = 0;

        foreach ((new ReflectionClass($clase))->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            if ($metodo->getNumberOfParameters() > 0 || $metodo->class !== $clase) {
                continue;
            }

            $tipo = $metodo->getReturnType();

            if (! $tipo instanceof ReflectionNamedType || ! is_subclass_of($tipo->getName(), Relation::class)) {
                continue;
            }

            $relacion = $modelo->{$metodo->getName()}();
            $relaciones++;

            $this->comprobar($relacion, "{$clase}::{$metodo->getName()}()");
        }

        $this->assertGreaterThanOrEqual(0, $relaciones);
    }

    private function comprobar(Relation $relacion, string $donde): void
    {
        $relacionado = $relacion->getRelated();

        $this->assertTrue(
            Schema::hasTable($relacionado->getTable()),
            "{$donde} apunta a la tabla inexistente '{$relacionado->getTable()}'.",
        );

        if ($relacion instanceof BelongsTo) {
            $this->assertColumna($relacion->getParent()->getTable(), $relacion->getForeignKeyName(), $donde);
            $this->assertColumna($relacionado->getTable(), $relacion->getOwnerKeyName(), $donde);

            return;
        }

        if ($relacion instanceof HasOneOrMany) {
            $this->assertColumna($relacionado->getTable(), $relacion->getForeignKeyName(), $donde);

            return;
        }

        if ($relacion instanceof BelongsToMany) {
            $this->assertTrue(
                Schema::hasTable($relacion->getTable()),
                "{$donde} usa la tabla pivote inexistente '{$relacion->getTable()}'.",
            );
            $this->assertColumna($relacion->getTable(), $relacion->getForeignPivotKeyName(), $donde);
            $this->assertColumna($relacion->getTable(), $relacion->getRelatedPivotKeyName(), $donde);
        }
    }

    private function assertColumna(string $tabla, string $columna, string $donde): void
    {
        $this->assertTrue(
            Schema::hasColumn($tabla, $columna),
            "{$donde} apunta a la columna inexistente '{$tabla}.{$columna}'.",
        );
    }
}
