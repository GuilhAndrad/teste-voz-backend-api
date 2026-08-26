<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Produto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ProdutoObserver
{
    public function created(Produto $produto): void
    {
        Log::info('Produto criado', [
            'id' => $produto->id,
            'nome' => $produto->nome,
            'categoria_id' => $produto->categoria_id,
        ]);
    }

    public function updated(Produto $produto): void
    {
        Log::info('Produto atualizado', [
            'id' => $produto->id,
            'alteracoes' => $produto->getChanges(),
        ]);

        Cache::forget("produtos:{$produto->id}");
    }

    public function deleted(Produto $produto): void
    {
        Log::info('Produto deletado', [
            'id' => $produto->id,
            'nome' => $produto->nome,
        ]);

        Cache::forget("produtos:{$produto->id}");
    }
}