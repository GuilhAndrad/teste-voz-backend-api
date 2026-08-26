<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Categoria;
use App\Observers\ProdutoObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nome',
    'descricao',
    'preco',
    'categoria_id',
])]
#[ObservedBy(ProdutoObserver::class)]
class Produto extends Model
{
    /** @use HasFactory<\Database\Factories\ProdutoFactory> */
    use HasFactory;

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'preco' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * @param  Builder<Produto>  $query
     * @return Builder<Produto>
     */
    public function scopeSearch(Builder $query, ?string $termo): Builder
    {
        return $query->when(
            filled($termo),
            fn (Builder $query): Builder => $query->where('nome', 'like', "%{$termo}%"),
        );
    }
}
