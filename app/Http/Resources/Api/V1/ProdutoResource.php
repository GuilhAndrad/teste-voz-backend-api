<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Controllers\Concerns\ResolvesIncludes;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Produto */
final class ProdutoResource extends JsonResource
{
    use ResolvesIncludes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'preco' => $this->preco,
            'categoria_id' => $this->categoria_id,
            'categoria' => $this->when(
                $this->wantsInclude($request, 'categoria'),
                fn () => CategoriaResource::make($this->whenLoaded('categoria')),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
