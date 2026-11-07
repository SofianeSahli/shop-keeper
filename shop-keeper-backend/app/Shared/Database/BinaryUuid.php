<?php

namespace App\Shared\Database;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

final class BinaryUuid implements CastsAttributes
{
    public static function toBytes(string $uuid): string
    {
        return Uuid::fromString($uuid)->getBytes();
    }

    public static function fromBytes(string $bytes): string
    {
        return Uuid::fromBytes($bytes)->toString();
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : self::fromBytes($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : self::toBytes($value);
    }
}
