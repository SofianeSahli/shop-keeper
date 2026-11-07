<?php

namespace App\Modules\Catalog\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SuggestionsRequest extends FormRequest
{
    private const MIN_SEARCH_LENGTH = 2;

    private const MAX_SEARCH_LENGTH = 120;

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:'.self::MIN_SEARCH_LENGTH, 'max:'.self::MAX_SEARCH_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'q.required',
            'q.string' => 'q.invalid',
            'q.min' => 'q.too_short',
            'q.max' => 'q.too_long',
        ];
    }
}
