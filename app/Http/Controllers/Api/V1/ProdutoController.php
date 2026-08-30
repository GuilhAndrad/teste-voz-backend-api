<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Produto\StoreProdutoRequest;
use App\Http\Requests\Api\V1\Produto\UpdateProdutoRequest;
use App\Http\Resources\Api\V1\ProdutoResource;
use App\Models\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProdutoController extends Controller
{
    use HandlesPagination;

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProdutoResource::collection(
            Produto::with('categoria')
                ->search($request->string('search')->value())
                ->latest()
                ->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreProdutoRequest $request): ProdutoResource
    {
        $produto = Produto::create($request->validated());

        return ProdutoResource::make($produto->loadMissing('categoria'));
    }

    public function show(Produto $produto): ProdutoResource
    {
        $produto->load('categoria');

        return ProdutoResource::make($produto->loadMissing('categoria'));
    }

    public function update(UpdateProdutoRequest $request, Produto $produto): ProdutoResource
    {
        $produto->update($request->validated());

        return ProdutoResource::make($produto->loadMissing('categoria'));
    }

    public function destroy(Produto $produto): JsonResponse
    {
        $produto->delete();

        return response()->json(status: 204);
    }
}
