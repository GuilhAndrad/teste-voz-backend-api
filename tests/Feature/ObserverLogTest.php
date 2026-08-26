<?php

declare(strict_types=1);

use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Support\Facades\Log;
use Mockery;

it('loga a criacao, atualizacao e delecao de categoria', function (): void {
    Log::spy();

    $categoria = Categoria::create(['nome' => 'Eletrônicos']);
    $categoria->update(['nome' => 'Eletrônicos Atualizado']);
    $categoria->delete();

    Log::shouldHaveReceived('info')->with('Categoria criada', Mockery::type('array'))->once();
    Log::shouldHaveReceived('info')->with('Categoria atualizada', Mockery::type('array'))->once();
    Log::shouldHaveReceived('info')->with('Categoria deletada', Mockery::type('array'))->once();
});

it('loga a criacao, atualizacao e delecao de produto', function (): void {
    Log::spy();

    $categoria = Categoria::factory()->create();
    $produto = Produto::create([
        'nome' => 'Notebook',
        'descricao' => null,
        'preco' => 3500,
        'categoria_id' => $categoria->id,
    ]);
    $produto->update(['preco' => 4000]);
    $produto->delete();

    Log::shouldHaveReceived('info')->with('Produto criado', Mockery::type('array'))->once();
    Log::shouldHaveReceived('info')->with('Produto atualizado', Mockery::type('array'))->once();
    Log::shouldHaveReceived('info')->with('Produto deletado', Mockery::type('array'))->once();
});
