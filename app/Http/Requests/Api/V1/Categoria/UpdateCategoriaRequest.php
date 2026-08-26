<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Categoria;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCategoriaRequest extends FormRequest
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
        ];
    }
}