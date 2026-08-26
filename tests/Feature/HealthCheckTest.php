<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDOException;

it('reporta status saudável quando o banco está acessível', function (): void {
    $this->getJson('/api/health-db')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'database' => 'up',
        ])
        ->assertJsonStructure(['status', 'database', 'timestamp']);
});

it('reporta status degradado quando o banco falha', function (): void {
    DB::shouldReceive('connection->getPdo')
        ->andThrow(new PDOException('conexão recusada'));

    $this->getJson('/api/health-db')
        ->assertStatus(503)
        ->assertJson([
            'status' => 'degraded',
            'database' => 'down',
        ]);
});
