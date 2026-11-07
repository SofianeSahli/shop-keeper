<?php

namespace App\Modules\Catalog\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListProductsRequest extends FormRequest
{
    private const DEFAULT_PAGE_SIZE = 50;

    private const MAX_PAGE_SIZE = 100;

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'between:1,'.self::MAX_PAGE_SIZE],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'per_page.invalid',
            'per_page.between' => 'per_page.invalid',
            'cursor.string' => 'cursor.invalid',
        ];
    }

    public function pageSize(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PAGE_SIZE);
    }
}
