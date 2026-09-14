<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ayudantes de esquema para lo que el Schema builder de Laravel no cubre.
 *
 * La especificación (§4.2) exige enums como `varchar` + `CHECK`, no como tipos
 * nativos de Postgres: los enums nativos son dolorosos de alterar. Y los
 * constraints de exclusión, que son lo que hace correcto el agendamiento bajo
 * concurrencia (§4.11), no existen en el builder.
 */
final class Esquema
{
    /**
     * Enum de la especificación: varchar + CHECK con la lista de valores.
     */
    public static function enum(string $tabla, string $columna, array $valores): void
    {
        $lista = implode(', ', array_map(
            fn (string $v) => "'".str_replace("'", "''", $v)."'",
            $valores,
        ));

        self::check($tabla, $columna, "{$columna} IN ({$lista})");
    }

    /**
     * CHECK arbitrario. El nombre se deriva de la columna o regla.
     */
    public static function check(string $tabla, string $nombre, string $expresion): void
    {
        DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_{$nombre}_check CHECK ({$expresion})");
    }

    /**
     * Columna generada y almacenada. Se usa para los rangos que alimentan los
     * constraints de exclusión.
     */
    public static function generada(string $tabla, string $columna, string $tipo, string $expresion): void
    {
        DB::statement("ALTER TABLE {$tabla} ADD COLUMN {$columna} {$tipo} GENERATED ALWAYS AS ({$expresion}) STORED");
    }

    /**
     * Constraint de exclusión sobre índice GiST.
     *
     * @param  array<string,string>  $columnas  columna => operador ('=' , '&&')
     */
    public static function exclusion(string $tabla, string $nombre, array $columnas, ?string $where = null): void
    {
        $partes = [];

        foreach ($columnas as $columna => $operador) {
            $partes[] = "{$columna} WITH {$operador}";
        }

        $sql = "ALTER TABLE {$tabla} ADD CONSTRAINT {$nombre} EXCLUDE USING gist (".implode(', ', $partes).')';

        if ($where !== null) {
            $sql .= " WHERE ({$where})";
        }

        DB::statement($sql);
    }
}
