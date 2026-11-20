<?php

namespace App\Modules\Purchasing\Controllers\Requests\Rules;

use App\Modules\Purchasing\Models\PaymentMethod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class PaymentRules
{
    public const MAX_AMOUNT_MILLIMES = 999_999_999_999;

    public const MAX_REFERENCE_LENGTH = 50;

    public static function amount(string $presence): array
    {
        return [$presence, 'integer:strict', 'min:1', 'max:'.self::MAX_AMOUNT_MILLIMES];
    }

    public static function method(string $presence): array
    {
        $paymentMethod = Rule::enum(PaymentMethod::class);

        return [$presence, $paymentMethod];
    }

    public static function reference(): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_REFERENCE_LENGTH];
    }

    public static function messages(string $prefix): array
    {
        return [
            "{$prefix}amount.required" => 'amount.required',
            "{$prefix}amount.required_with" => 'amount.required',
            "{$prefix}amount.integer" => 'amount.integer',
            "{$prefix}amount.min" => 'amount.too_low',
            "{$prefix}amount.max" => 'amount.too_high',
            "{$prefix}method.required" => 'method.required',
            "{$prefix}method.required_with" => 'method.required',
            "{$prefix}method.".Enum::class => 'method.invalid',
            "{$prefix}reference.string" => 'reference.invalid',
            "{$prefix}reference.max" => 'reference.too_long',
        ];
    }
}
