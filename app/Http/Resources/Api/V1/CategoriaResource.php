<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Controllers\Concerns\ResolvesIncludes;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Categoria */
final class CategoriaResource extends JsonResource
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
            'produtos' => $this->when(
                $this->wantsInclude($request, 'produtos'),
                fn () => ProdutoResource::collection($this->whenLoaded('produtos')),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
