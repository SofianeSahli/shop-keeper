<?php

namespace App\Modules\Catalog\Support;

final readonly class Barcode
{
    private const EAN_8_LENGTH = 8;

    private const UPC_A_LENGTH = 12;

    private const EAN_13_LENGTH = 13;

    private const IN_STORE_PREFIX = '2';

    private function __construct(public string $value) {}

    public static function parse(string $raw): self
    {
        $digits = str_replace(' ', '', $raw);

        if (preg_match('/^\d+$/', $digits) !== 1
            || ! in_array(strlen($digits), [self::EAN_8_LENGTH, self::UPC_A_LENGTH, self::EAN_13_LENGTH], true)
            || self::checkDigit(substr($digits, 0, -1)) !== $digits[-1]) {
            throw new InvalidBarcode(InvalidBarcode::MALFORMED);
        }

        $stored = strlen($digits) === self::UPC_A_LENGTH ? '0'.$digits : $digits;

        if (strlen($stored) === self::EAN_13_LENGTH && str_starts_with($stored, self::IN_STORE_PREFIX)) {
            throw new InvalidBarcode(InvalidBarcode::IN_STORE);
        }

        return new self($stored);
    }

    public static function checkDigit(string $digitsWithoutCheck): string
    {
        $sum = 0;

        foreach (array_reverse(str_split($digitsWithoutCheck)) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (string) ((10 - $sum % 10) % 10);
    }
}
