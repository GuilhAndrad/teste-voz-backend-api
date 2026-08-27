<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Produto\StoreProdutoRequest;
use App\Http\Requests\Api\V1\Produto\UpdateProdutoRequest;
use App\Http\Resources\Api\V1\ProdutoResource;
use App\Models\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

final class ProdutoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProdutoResource::collection(
            Produto::with('categoria')
                ->search($request->string('search')->value())
                ->latest()
                ->paginate(15)
        );
    }

    public function store(StoreProdutoRequest $request): JsonResponse
    {
        $produto = Produto::create($request->validated());

        return ProdutoResource::make($produto->load('categoria'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Produto $produto): ProdutoResource
    {
        $produto = Cache::remember(
            "produtos:{$produto->id}",
            now()->addMinutes(10),
            fn (): Produto => $produto->load('categoria'),
        );

        return ProdutoResource::make($produto);
    }

    public function update(UpdateProdutoRequest $request, Produto $produto): ProdutoResource
    {
        $produto->update($request->validated());

        return ProdutoResource::make($produto->load('categoria'));
    }

    public function destroy(Produto $produto): JsonResponse
    {
        $produto->delete();

        return response()->json(status: 204);
    }
}
