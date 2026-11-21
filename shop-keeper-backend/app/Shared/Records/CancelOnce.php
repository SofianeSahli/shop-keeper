<?php

namespace App\Shared\Records;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelOnce
{
    private const ID_FIELD = 'id';

    /**
     * @param  Closure(Model): void  $afterCancelling
     */
    public function handle(Model $record, Model $cancellation, string $recordColumn, Closure $afterCancelling): Created
    {
        $recordId = $record->uuid();

        return DB::transaction(function () use ($record, $recordId, $cancellation, $recordColumn, $afterCancelling): Created {
            $locked = $record->newQuery()->whereUuid($recordId)->lockForUpdate()->firstOrFail();
            $lockedBytes = $locked->getKey();
            $isAlreadyCancelled = $cancellation->newQuery()->where($recordColumn, $lockedBytes)->lockForUpdate()->exists();

            if ($isAlreadyCancelled) {
                return new Created($locked, false);
            }

            $cancellationId = $cancellation->uuid();
            $cancellationQuery = $cancellation->newQuery();
            RecordWithSameId::refuseIfUsed($cancellationQuery, $cancellationId, self::ID_FIELD);
            $this->insert($cancellation);
            $afterCancelling($locked);

            return new Created($locked, true);
        });
    }

    private function insert(Model $cancellation): void
    {
        try {
            $cancellation->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([self::ID_FIELD => [RecordWithSameId::TAKEN]]);
        }
    }
}
