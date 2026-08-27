<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Categoria\StoreCategoriaRequest;
use App\Http\Requests\Api\V1\Categoria\UpdateCategoriaRequest;
use App\Http\Resources\Api\V1\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

final class CategoriaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return CategoriaResource::collection(
            Categoria::search($request->string('search')->value())
                ->latest()
                ->paginate(15)
        );
    }

    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        $categoria = Categoria::create($request->validated());

        return CategoriaResource::make($categoria)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Categoria $categoria): CategoriaResource
    {
        $categoria = Cache::remember(
            "categorias:{$categoria->id}",
            now()->addMinutes(10),
            fn (): Categoria => $categoria->load('produtos'),
        );

        return CategoriaResource::make($categoria);
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria): CategoriaResource
    {
        $categoria->update($request->validated());

        return CategoriaResource::make($categoria);
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        $categoria->delete();

        return response()->json(status: 204);
    }
}
