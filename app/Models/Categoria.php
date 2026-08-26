<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CategoriaObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome'])]
#[ObservedBy(CategoriaObserver::class)]
class Categoria extends Model
{
    /** @use HasFactory<\Database\Factories\CategoriaFactory> */
    use HasFactory;

    /**
     * @return HasMany<Produto, $this>
     */
    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    /**
     * @param  Builder<Categoria>  $query
     * @return Builder<Categoria>
     */
    public function scopeSearch(Builder $query, ?string $termo): Builder
    {
        return $query->when(
            filled($termo),
            fn(Builder $query): Builder => $query->where('nome', 'like', "%{$termo}%"),
        );
    }
}