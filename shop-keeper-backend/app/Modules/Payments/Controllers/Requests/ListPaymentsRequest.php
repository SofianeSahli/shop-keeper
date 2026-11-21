<?php

namespace App\Modules\Payments\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListPaymentsRequest extends FormRequest
{
    private const DEFAULT_PAGE_SIZE = 50;

    private const MAX_PAGE_SIZE = 100;

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'between:1,'.self::MAX_PAGE_SIZE],
            'cursor' => ['sometimes', 'string'],
            'contact_id' => ['sometimes', 'string', 'uuid'],
            'transaction_id' => ['sometimes', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'per_page.invalid',
            'per_page.between' => 'per_page.invalid',
            'cursor.string' => 'cursor.invalid',
            'contact_id.string' => 'contact_id.invalid',
            'contact_id.uuid' => 'contact_id.invalid',
            'transaction_id.string' => 'transaction_id.invalid',
            'transaction_id.uuid' => 'transaction_id.invalid',
        ];
    }

    public function pageSize(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PAGE_SIZE);
    }

    public function contactId(): ?string
    {
        $contactId = $this->validated('contact_id');

        return $contactId === null ? null : strtolower($contactId);
    }

    public function transactionId(): ?string
    {
        $transactionId = $this->validated('transaction_id');

        return $transactionId === null ? null : strtolower($transactionId);
    }
}
