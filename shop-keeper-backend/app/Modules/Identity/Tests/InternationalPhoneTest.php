<?php

use App\Modules\Identity\Rules\InternationalPhone;
use Illuminate\Support\Facades\Validator;

function phoneFailures(mixed $phone): array
{
    return Validator::make(['phone' => $phone], ['phone' => [new InternationalPhone]])
        ->errors()
        ->get('phone');
}

it('saves one standard form for any spelling', function (string $spelling, string $standard) {
    expect(InternationalPhone::normalize($spelling))->toBe($standard);
    expect(phoneFailures($spelling))->toBe([]);
})->with([
    ['+216 20 123 456', '+21620123456'],
    ['0021620123456', '+21620123456'],
    ['+33 6 12 34 56 78', '+33612345678'],
    ['(+1) 202-555-0143', '+12025550143'],
]);

it('refuses a number with no valid dialing code or length', function (mixed $phone) {
    expect(phoneFailures($phone))->toBe(['phone.invalid']);
})->with([
    ['20123456'],
    ['+0123456'],
    ['+12345'],
    ['+1234567890123456'],
    [12345678],
]);
