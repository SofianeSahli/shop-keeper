<?php

namespace App\Modules\Catalog\Controllers\Requests\Rules;

final class PriceInMillimes
{
    public const MAX_PRICE_MILLIMES = 999_999_999;

    public static function rules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'integer:strict',
            'min:0',
            'max:'.self::MAX_PRICE_MILLIMES,
        ];
    }

    public static function messages(string $field): array
    {
        return [
            "{$field}.required" => "{$field}.required",
            "{$field}.integer" => "{$field}.integer",
            "{$field}.min" => "{$field}.negative",
            "{$field}.max" => "{$field}.too_high",
        ];
    }
}
