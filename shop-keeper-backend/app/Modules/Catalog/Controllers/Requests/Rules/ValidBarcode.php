<?php

namespace App\Modules\Catalog\Controllers\Requests\Rules;

use App\Modules\Catalog\Support\Barcode;
use App\Modules\Catalog\Support\InvalidBarcode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidBarcode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(InvalidBarcode::MALFORMED);

            return;
        }

        try {
            Barcode::parse($value);
        } catch (InvalidBarcode $failure) {
            $fail($failure->fieldCode);
        }
    }
}
