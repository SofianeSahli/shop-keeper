<?php

namespace App\Modules\Purchasing\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class RecordWithSameId
{
    public const TAKEN = 'id.taken';

    public static function find(Builder $query, string $id, int $shopId, string $field = 'id', bool $readLatest = false): ?Model
    {
        $record = $query
            ->whereUuid($id)
            ->when($readLatest, fn (Builder $latest) => $latest->lockForUpdate())
            ->first();

        if ($record !== null && $record->shop_id !== $shopId) {
            throw ValidationException::withMessages([$field => [self::TAKEN]]);
        }

        return $record;
    }

    public static function refuseIfUsed(Builder $query, string $id, string $field): void
    {
        $isUsed = $query->whereUuid($id)->exists();

        if ($isUsed) {
            throw ValidationException::withMessages([$field => [self::TAKEN]]);
        }
    }
}
