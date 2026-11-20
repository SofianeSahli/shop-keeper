<?php

namespace App\Modules\Purchasing\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListRequest extends FormRequest
{
    private const DEFAULT_PAGE_SIZE = 50;

    private const MAX_PAGE_SIZE = 100;

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'between:1,'.self::MAX_PAGE_SIZE],
            'cursor' => ['sometimes', 'string'],
            'supplier_id' => ['sometimes', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'per_page.invalid',
            'per_page.between' => 'per_page.invalid',
            'cursor.string' => 'cursor.invalid',
            'supplier_id.string' => 'supplier_id.invalid',
            'supplier_id.uuid' => 'supplier_id.invalid',
        ];
    }

    public function pageSize(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PAGE_SIZE);
    }

    public function supplierId(): ?string
    {
        $supplierId = $this->validated('supplier_id');

        return $supplierId === null ? null : strtolower($supplierId);
    }
}
