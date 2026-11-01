<?php

namespace App\Modules\Identity\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class InternationalPhone implements ValidationRule
{
    private const E164_PATTERN = '/^\+[1-9]\d{6,14}$/D';

    public static function normalize(string $phone): string
    {
        $compact = preg_replace('/[\s\-()]/', '', $phone);

        return str_starts_with($compact, '00') ? '+'.substr($compact, 2) : $compact;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::E164_PATTERN, self::normalize($value)) !== 1) {
            $fail('phone.invalid');
        }
    }
}
