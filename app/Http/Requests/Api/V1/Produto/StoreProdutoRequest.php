<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Produto;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'preco' => ['required', 'numeric', 'min:0'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
        ];
    }
}
