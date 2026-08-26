<?php

declare(strict_types=1);

use App\Models\Categoria;
use App\Models\Produto;

it('lista categorias', function (): void {
    Categoria::factory()->count(3)->create();

    $this->getJson('/api/v1/categorias')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('cria uma categoria', function (): void {
    $payload = ['nome' => 'Eletrônicos'];

    $this->postJson('/api/v1/categorias', $payload)
        ->assertCreated()
        ->assertJsonPath('data.nome', 'Eletrônicos');

    $this->assertDatabaseHas('categorias', $payload);
});

it('exige nome ao criar categoria', function (): void {
    $this->postJson('/api/v1/categorias', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nome');
});

it('exibe uma categoria', function (): void {
    $categoria = Categoria::factory()->create();

    $this->getJson("/api/v1/categorias/{$categoria->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $categoria->id);
});

it('atualiza uma categoria', function (): void {
    $categoria = Categoria::factory()->create();

    $this->putJson("/api/v1/categorias/{$categoria->id}", ['nome' => 'Atualizada'])
        ->assertOk()
        ->assertJsonPath('data.nome', 'Atualizada');

    $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'nome' => 'Atualizada']);
});

it('deleta uma categoria', function (): void {
    $categoria = Categoria::factory()->create();

    $this->deleteJson("/api/v1/categorias/{$categoria->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
});

it('retorna 404 padronizado para categoria inexistente', function (): void {
    $this->getJson('/api/v1/categorias/999')
        ->assertNotFound()
        ->assertJson(['message' => 'Registro não encontrado.']);
});

it('deleta produtos em cascata ao deletar a categoria', function (): void {
    $categoria = Categoria::factory()->has(Produto::factory(3))->create();

    $this->deleteJson("/api/v1/categorias/{$categoria->id}")
        ->assertNoContent();

    $this->assertDatabaseCount('produtos', 0);
});
