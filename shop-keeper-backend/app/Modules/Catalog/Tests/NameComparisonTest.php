<?php

use App\Modules\Catalog\Support\NameComparison;

it('compares case, end spaces and a final s as the same name', function (string $name) {
    expect(NameComparison::compared($name))->toBe('tomate');
})->with(['Tomates', 'tomate', ' TOMATE ', 'tomateS']);

it('ignores French accents and expands ligatures', function () {
    expect(NameComparison::compared('Crème'))->toBe(NameComparison::compared('creme'));
    expect(NameComparison::compared('œufs'))->toBe(NameComparison::compared('oeufs'));
    expect(NameComparison::compared('Bœuf'))->toBe('boeuf');
});

it('drops a final x from a Latin-script word', function () {
    expect(NameComparison::compared('Choux'))->toBe('chou');
});

it('compares the alef variants as a bare alef', function (string $alef) {
    expect(NameComparison::compared($alef.'رز'))->toBe('ارز');
})->with(['أ', 'إ', 'آ', 'ا', 'ٱ']);

it('compares teh marbuta as heh and alef maksura as yeh', function () {
    expect(NameComparison::compared('طماطة'))->toBe(NameComparison::compared('طماطه'));
    expect(NameComparison::compared('حلوى'))->toBe(NameComparison::compared('حلوي'));
});

it('ignores Arabic short vowels, shadda, sukun and tatweel', function () {
    expect(NameComparison::compared('سُكَّر'))->toBe('سكر');
    expect(NameComparison::compared('خُبْز'))->toBe('خبز');
    expect(NameComparison::compared('حـلـيـب'))->toBe('حليب');
});

it('never drops a final letter from an Arabic word', function () {
    expect(NameComparison::compared('عدس'))->toBe('عدس');
});

it('treats punctuation as a space', function () {
    expect(NameComparison::compared('pomme-de-terre'))->toBe(NameComparison::compared('pomme de terre'));
    expect(NameComparison::compared("lait d'or"))->toBe('lait d or');
});

it('keeps single letters and digits', function () {
    expect(NameComparison::compared('Eau 1.5 L'))->toBe('eau 1 5 l');
});

it('gives an identity key without spaces', function () {
    expect(NameComparison::identityKey('Tomates cerises'))->toBe('tomatecerise');
    expect(NameComparison::identityKey('tomate  cerise'))->toBe(NameComparison::identityKey('Tomates cerises'));
});

it('gives an empty form for a name of only spaces', function () {
    expect(NameComparison::compared('   '))->toBe('');
});
