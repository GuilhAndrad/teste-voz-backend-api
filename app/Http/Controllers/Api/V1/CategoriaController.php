<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Categoria\StoreCategoriaRequest;
use App\Http\Requests\Api\V1\Categoria\UpdateCategoriaRequest;
use App\Http\Resources\Api\V1\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoriaController extends Controller
{
    use HandlesPagination;

    public function index(Request $request): AnonymousResourceCollection
    {
        return CategoriaResource::collection(
            Categoria::search($request->string('search')->value())
                ->latest()
                ->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreCategoriaRequest $request): CategoriaResource
    {
        $categoria = Categoria::create($request->validated());

        return CategoriaResource::make($categoria);
    }

    public function show(Categoria $categoria): CategoriaResource
    {
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
