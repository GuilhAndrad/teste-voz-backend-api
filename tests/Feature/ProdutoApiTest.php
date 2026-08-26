<?php

declare(strict_types=1);

use App\Models\Categoria;
use App\Models\Produto;

it('lista produtos', function (): void {
    Produto::factory()->count(3)->create();

    $this->getJson('/api/v1/produtos')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertHeader('X-Api-Version', 'v1');
});

it('cria um produto', function (): void {
    $categoria = Categoria::factory()->create();

    $payload = [
        'nome' => 'Notebook',
        'descricao' => 'Notebook 15 polegadas',
        'preco' => 3500.90,
        'categoria_id' => $categoria->id,
    ];

    $this->postJson('/api/v1/produtos', $payload)
        ->assertCreated()
        ->assertJsonPath('data.nome', 'Notebook')
        ->assertJsonPath('data.categoria.id', $categoria->id);
});

it('exige campos obrigatorios ao criar produto', function (): void {
    $this->postJson('/api/v1/produtos', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nome', 'preco', 'categoria_id']);
});

it('rejeita categoria inexistente', function (): void {
    $this->postJson('/api/v1/produtos', [
        'nome' => 'Produto',
        'preco' => 10,
        'categoria_id' => 999,
    ])->assertJsonValidationErrors('categoria_id');
});

it('rejeita preco negativo ao criar produto', function (): void {
    $categoria = Categoria::factory()->create();

    $this->postJson('/api/v1/produtos', [
        'nome' => 'Produto',
        'preco' => -10,
        'categoria_id' => $categoria->id,
    ])->assertJsonValidationErrors('preco');
});

it('exibe um produto', function (): void {
    $produto = Produto::factory()->create();

    $this->getJson("/api/v1/produtos/{$produto->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $produto->id);
});

it('atualiza um produto', function (): void {
    $produto = Produto::factory()->create();
    $novaCategoria = Categoria::factory()->create();

    $payload = [
        'nome' => 'Notebook Atualizado',
        'descricao' => 'Nova descrição',
        'preco' => 4200,
        'categoria_id' => $novaCategoria->id,
    ];

    $this->putJson("/api/v1/produtos/{$produto->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.nome', 'Notebook Atualizado');
});

it('deleta um produto', function (): void {
    $produto = Produto::factory()->create();

    $this->deleteJson("/api/v1/produtos/{$produto->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('produtos', ['id' => $produto->id]);
});

it('retorna 404 padronizado para produto inexistente', function (): void {
    $this->getJson('/api/v1/produtos/999')
        ->assertNotFound()
        ->assertJson(['message' => 'Registro não encontrado.']);
});

it('filtra produtos pelo nome via scope de busca', function (): void {
    Produto::factory()->create(['nome' => 'Notebook Gamer']);
    Produto::factory()->create(['nome' => 'Cadeira Gamer']);
    Produto::factory()->create(['nome' => 'Mouse sem Fio']);

    $this->getJson('/api/v1/produtos?search=Gamer')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('invalida o cache do produto ao atualizar', function (): void {
    $produto = Produto::factory()->create(['nome' => 'Nome Original']);

    $this->getJson("/api/v1/produtos/{$produto->id}")
        ->assertJsonPath('data.nome', 'Nome Original');

    $this->putJson("/api/v1/produtos/{$produto->id}", [
        'nome' => 'Nome Atualizado',
        'descricao' => $produto->descricao,
        'preco' => $produto->preco,
        'categoria_id' => $produto->categoria_id,
    ])->assertOk();

    $this->getJson("/api/v1/produtos/{$produto->id}")
        ->assertJsonPath('data.nome', 'Nome Atualizado');
});
