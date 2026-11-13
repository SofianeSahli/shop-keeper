<?php

use App\Modules\Catalog\Support\Barcode;
use App\Modules\Catalog\Support\InvalidBarcode;

function barcodeFailure(string $raw): ?string
{
    try {
        Barcode::parse($raw);

        return null;
    } catch (InvalidBarcode $failure) {
        return $failure->fieldCode;
    }
}

it('accepts EAN-13, EAN-8 and UPC-A', function (string $raw, string $stored) {
    expect(Barcode::parse($raw)->value)->toBe($stored);
})->with([
    'EAN-13' => ['6191234567897', '6191234567897'],
    'EAN-8' => ['96385074', '96385074'],
    'UPC-A' => ['036000291452', '0036000291452'],
]);

it('removes spaces', function () {
    expect(Barcode::parse('619 1234 56789 7')->value)->toBe('6191234567897');
});

it('stores a UPC-A as the EAN-13 with a leading zero', function () {
    expect(Barcode::parse('036000291452')->value)->toBe(Barcode::parse('0036000291452')->value);
});

it('refuses a wrong check digit, other lengths and letters', function (string $raw) {
    expect(barcodeFailure($raw))->toBe('barcode.invalid');
})->with(['6191234567890', '96385075', '12345', '61912345678971', '619123456789A', '']);

it('refuses an in-store code', function () {
    $digits = '200123456789';

    expect(barcodeFailure($digits.Barcode::checkDigit($digits)))->toBe('barcode.in_store');
});
