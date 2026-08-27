<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produto>
 */
final class ProdutoFactory extends Factory
{
    protected $model = Produto::class;

    private const NOMES = [
        'Notebook',
        'Smartphone',
        'Mouse sem Fio',
        'Teclado Mecânico',
        'Monitor 24"',
        'Fone de Ouvido Bluetooth',
        'Cadeira Gamer',
        'Câmera Digital',
        'Tablet',
        'Caixa de Som Portátil',
        'Smartwatch',
        'Impressora Multifuncional',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->randomElement(self::NOMES),
            'descricao' => fake()->sentence(10),
            'preco' => fake()->randomFloat(2, 29.9, 4999.9),
            'categoria_id' => Categoria::factory(),
        ];
    }
}
