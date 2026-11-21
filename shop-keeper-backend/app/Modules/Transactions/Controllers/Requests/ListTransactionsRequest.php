<?php

namespace App\Modules\Transactions\Controllers\Requests;

use App\Modules\Transactions\Models\TransactionKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class ListTransactionsRequest extends FormRequest
{
    private const DEFAULT_PAGE_SIZE = 50;

    private const MAX_PAGE_SIZE = 100;

    public function rules(): array
    {
        $transactionKind = Rule::enum(TransactionKind::class);

        return [
            'per_page' => ['sometimes', 'integer', 'between:1,'.self::MAX_PAGE_SIZE],
            'cursor' => ['sometimes', 'string'],
            'kind' => ['sometimes', $transactionKind],
            'contact_id' => ['sometimes', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'per_page.invalid',
            'per_page.between' => 'per_page.invalid',
            'cursor.string' => 'cursor.invalid',
            'kind.'.Enum::class => 'kind.invalid',
            'contact_id.string' => 'contact_id.invalid',
            'contact_id.uuid' => 'contact_id.invalid',
        ];
    }

    public function pageSize(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PAGE_SIZE);
    }

    public function kind(): ?TransactionKind
    {
        $kind = $this->validated('kind');

        return $kind === null ? null : TransactionKind::from($kind);
    }

    public function contactId(): ?string
    {
        $contactId = $this->validated('contact_id');

        return $contactId === null ? null : strtolower($contactId);
    }
}
