<?php

namespace App\Modules\Catalog\Support;

use RuntimeException;

final class InvalidBarcode extends RuntimeException
{
    public const MALFORMED = 'barcode.invalid';

    public const IN_STORE = 'barcode.in_store';

    public function __construct(public readonly string $fieldCode)
    {
        parent::__construct($fieldCode);
    }
}
