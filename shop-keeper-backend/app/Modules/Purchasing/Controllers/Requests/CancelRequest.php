<?php

namespace App\Modules\Purchasing\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
        ];
    }

    public function cancellationId(): string
    {
        $id = $this->validated('id');

        return strtolower($id);
    }
}
