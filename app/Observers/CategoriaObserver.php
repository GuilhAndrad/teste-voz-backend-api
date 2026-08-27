<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Categoria;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class CategoriaObserver
{
    public function created(Categoria $categoria): void
    {
        Log::info('Categoria criada', [
            'id' => $categoria->id,
            'nome' => $categoria->nome,
        ]);
    }

    public function updated(Categoria $categoria): void
    {
        Log::info('Categoria atualizada', [
            'id' => $categoria->id,
            'alteracoes' => $categoria->getChanges(),
        ]);

        Cache::forget("categorias:{$categoria->id}");
    }

    public function deleted(Categoria $categoria): void
    {
        Log::info('Categoria deletada', [
            'id' => $categoria->id,
            'nome' => $categoria->nome,
        ]);

        Cache::forget("categorias:{$categoria->id}");
    }
}
