<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\ProdutoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Categoria */
final class CategoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'produtos' => ProdutoResource::collection($this->whenLoaded('produtos')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}