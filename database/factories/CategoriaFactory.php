<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
final class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    private const NOMES = [
        'Eletrônicos',
        'Informática',
        'Livros',
        'Roupas e Acessórios',
        'Casa e Decoração',
        'Esporte e Lazer',
        'Beleza e Cuidados Pessoais',
        'Brinquedos',
        'Alimentos e Bebidas',
        'Móveis',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->randomElement(self::NOMES),
        ];
    }
}