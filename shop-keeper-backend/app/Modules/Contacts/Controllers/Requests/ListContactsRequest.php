<?php

namespace App\Modules\Contacts\Controllers\Requests;

use App\Modules\Contacts\Models\ContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class ListContactsRequest extends FormRequest
{
    private const DEFAULT_PAGE_SIZE = 50;

    private const MAX_PAGE_SIZE = 100;

    public function rules(): array
    {
        $contactRole = Rule::enum(ContactRole::class);

        return [
            'per_page' => ['sometimes', 'integer', 'between:1,'.self::MAX_PAGE_SIZE],
            'cursor' => ['sometimes', 'string'],
            'role' => ['sometimes', $contactRole],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'per_page.invalid',
            'per_page.between' => 'per_page.invalid',
            'cursor.string' => 'cursor.invalid',
            'role.'.Enum::class => 'role.invalid',
        ];
    }

    public function pageSize(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PAGE_SIZE);
    }

    public function role(): ?ContactRole
    {
        $role = $this->validated('role');

        return $role === null ? null : ContactRole::from($role);
    }
}
