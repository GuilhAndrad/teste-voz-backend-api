<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Produto */
final class ProdutoResource extends JsonResource
{
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
            'categoria' => new CategoriaResource($this->whenLoaded('categoria')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}