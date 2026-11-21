<?php

namespace App\Shared\Records;

use Illuminate\Database\Eloquent\Model;

final class CancellationResource
{
    /**
     * @return array{reason: ?string, cancelled_by: int, cancelled_at: string}|null
     */
    public static function of(Model $record): ?array
    {
        if ($record->cancelled_at === null) {
            return null;
        }

        $cancelledAt = $record->cancelled_at->toIso8601ZuluString();

        return [
            'reason' => $record->cancellation_reason,
            'cancelled_by' => $record->cancelled_by,
            'cancelled_at' => $cancelledAt,
        ];
    }
}
