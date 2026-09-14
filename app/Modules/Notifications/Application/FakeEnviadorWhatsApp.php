<?php

namespace App\Modules\Notifications\Application;

use App\Models\PlantillaWhatsapp;
use App\Modules\Notifications\Application\Contracts\EnviadorWhatsApp;
use Illuminate\Support\Str;

/**
 * Doble de prueba de `EnviadorWhatsApp`, mismo criterio que `FakeEnviadorPush`.
 */
class FakeEnviadorWhatsApp implements EnviadorWhatsApp
{
    /** @var list<array{telefono:string, plantilla:string, variables:array<string,string>}> */
    private static array $enviados = [];

    public function enviar(string $telefono, PlantillaWhatsapp $plantilla, array $variables): string
    {
        self::$enviados[] = ['telefono' => $telefono, 'plantilla' => $plantilla->nombre, 'variables' => $variables];

        return (string) Str::uuid();
    }

    /** @return list<array{telefono:string, plantilla:string, variables:array<string,string>}> */
    public static function enviados(): array
    {
        return self::$enviados;
    }

    public static function reset(): void
    {
        self::$enviados = [];
    }
}
