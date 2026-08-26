<?php

declare(strict_types=1);

use App\Models\Categoria;
use App\Models\Produto;
use InvalidArgumentException;

it('normaliza o preco para string com duas casas decimais', function (): void {
    $produto = Produto::factory()->create([
        'categoria_id' => Categoria::factory(),
        'preco' => 1500,
    ]);

    expect($produto->fresh()->preco)->toBe('1500.00');
});

it('lanca excecao ao tentar salvar um preco nao numerico', function (): void {
    $categoria = Categoria::factory()->create();

    expect(fn () => Produto::create([
        'nome' => 'Produto',
        'descricao' => null,
        'preco' => 'abc',
        'categoria_id' => $categoria->id,
    ]))->toThrow(InvalidArgumentException::class);
});
