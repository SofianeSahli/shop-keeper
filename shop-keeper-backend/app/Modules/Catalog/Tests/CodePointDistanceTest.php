<?php

use App\Modules\Catalog\Support\CodePointDistance;
use App\Modules\Catalog\Support\NameComparison;

it('counts one per inserted, removed or replaced letter', function (string $first, string $second, int $distance) {
    expect(CodePointDistance::between($first, $second))->toBe($distance);
})->with([
    ['tomate', 'tomate', 0],
    ['tomate', 'tomat', 1],
    ['tomat', 'tomate', 1],
    ['tomate', 'tomade', 1],
    ['', 'abc', 3],
    ['carotte', 'carote', 1],
]);

it('counts Arabic letters, not bytes', function () {
    expect(CodePointDistance::between('طماطم', 'طماطا'))->toBe(1);
    expect(levenshtein('طماطم', 'طماطا'))->toBeGreaterThan(1);
    expect(CodePointDistance::between(NameComparison::compared('طماطم'), NameComparison::compared('طماطة')))->toBe(1);
});
