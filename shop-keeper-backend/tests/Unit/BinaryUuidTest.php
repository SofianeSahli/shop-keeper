<?php

use App\Shared\Database\BinaryUuid;
use Ramsey\Uuid\Exception\InvalidUuidStringException;

it('stores a uuid as 16 bytes and reads back the same lowercase string', function () {
    $uuid = '0190F3A2-6B1C-7D4E-9A10-3C5E7F812B44';

    $bytes = BinaryUuid::toBytes($uuid);

    expect(strlen($bytes))->toBe(16);
    expect(BinaryUuid::fromBytes($bytes))->toBe(strtolower($uuid));
});

it('refuses a string that is not a uuid', function () {
    BinaryUuid::toBytes('not-a-uuid');
})->throws(InvalidUuidStringException::class);
