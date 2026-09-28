<?php

namespace Database\Factories;

use App\Models\DocumentoLegal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoLegal>
 */
class DocumentoLegalFactory extends Factory
{
    protected $model = DocumentoLegal::class;

    public function definition(): array
    {
        return [
            'tipo' => 'politica_privacidad',
            'version' => fake()->unique()->numerify('####-##'),
            'contenido' => fake()->paragraphs(3, true),
            'vigente_desde' => now()->subDay(),
        ];
    }
}
