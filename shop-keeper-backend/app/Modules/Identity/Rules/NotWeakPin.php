<?php

namespace App\Modules\Identity\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NotWeakPin implements ValidationRule
{
    private const FORMAT_PATTERN = '/^[0-9]{6}$/D';

    private const ASCENDING_DIGITS = '0123456789';

    private const DESCENDING_DIGITS = '9876543210';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::FORMAT_PATTERN, $value) !== 1) {
            $fail('pin.format');

            return;
        }

        if ($this->isOneRepeatedDigit($value) || $this->isStraightRun($value)) {
            $fail('pin.weak');
        }
    }

    private function isOneRepeatedDigit(string $pin): bool
    {
        return count(array_unique(str_split($pin))) === 1;
    }

    private function isStraightRun(string $pin): bool
    {
        return str_contains(self::ASCENDING_DIGITS, $pin) || str_contains(self::DESCENDING_DIGITS, $pin);
    }
}
