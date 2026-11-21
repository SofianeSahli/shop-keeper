<?php

namespace App\Modules\Transactions\Controllers\Requests;

use App\Shared\Records\NewCancellation;
use Illuminate\Foundation\Http\FormRequest;

final class CancelRequest extends FormRequest
{
    private const MAX_REASON_LENGTH = 255;

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'uuid'],
            'reason' => ['nullable', 'string', 'max:'.self::MAX_REASON_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            'reason.string' => 'reason.invalid',
            'reason.max' => 'reason.too_long',
        ];
    }

    public function newCancellation(int $accountId): NewCancellation
    {
        $id = $this->validated('id');
        $lowercaseId = strtolower($id);
        $reason = $this->validated('reason');

        return new NewCancellation($lowercaseId, $reason, $accountId);
    }
}
