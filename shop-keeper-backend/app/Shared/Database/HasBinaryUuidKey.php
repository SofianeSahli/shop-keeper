<?php

namespace App\Shared\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasBinaryUuidKey
{
    public function initializeHasBinaryUuidKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
        $this->mergeCasts([$this->getKeyName() => BinaryUuid::class]);
    }

    public function getKey(): mixed
    {
        return $this->getAttributes()[$this->getKeyName()] ?? null;
    }

    public function scopeWhereUuid(Builder $query, string $uuid): void
    {
        if (! Str::isUuid($uuid)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($this->getKeyName(), BinaryUuid::toBytes($uuid));
    }
}
