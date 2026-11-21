<?php

namespace App\Modules\Payments\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable(['id', 'shop_id', 'direction', 'contact_id', 'transaction_id', 'amount', 'method', 'payment_date', 'reference', 'created_by'])]
class Payment extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    public const MAX_AMOUNT_MILLIMES = 999_999_999_999;

    public const MAX_REFERENCE_LENGTH = 50;

    public function scopeOfShop(Builder $query, int $shopId): void
    {
        $query->where('payments.shop_id', $shopId);
    }

    public function scopeOfContact(Builder $query, ?string $contactId): void
    {
        if ($contactId === null) {
            return;
        }

        $contactBytes = BinaryUuid::toBytes($contactId);
        $query->where('contact_id', $contactBytes);
    }

    public function scopeOfTransaction(Builder $query, ?string $transactionId): void
    {
        if ($transactionId === null) {
            return;
        }

        $transactionBytes = BinaryUuid::toBytes($transactionId);
        $query->where('transaction_id', $transactionBytes);
    }

    public function scopeNotCancelled(Builder $query): void
    {
        $query->whereNotExists(function (QueryBuilder $cancellations): void {
            $cancellations->selectRaw('1')
                ->from('payment_cancellations')
                ->whereColumn('payment_cancellations.payment_id', 'payments.id');
        });
    }

    public function scopeWithCancellation(Builder $query): void
    {
        $cancellation = fn (string $column) => PaymentCancellation::query()
            ->select($column)
            ->whereColumn('payment_cancellations.payment_id', 'payments.id');

        $cancellationReason = $cancellation('reason');
        $cancelledBy = $cancellation('cancelled_by');
        $cancelledAt = $cancellation('created_at');

        $query->select('payments.*')->addSelect([
            'cancellation_reason' => $cancellationReason,
            'cancelled_by' => $cancelledBy,
            'cancelled_at' => $cancelledAt,
        ]);
    }

    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('payment_date')->orderByDesc('sort_key');
    }

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'method' => PaymentMethod::class,
            'contact_id' => BinaryUuid::class,
            'transaction_id' => BinaryUuid::class,
            'amount' => 'integer',
            'created_by' => 'integer',
            'cancelled_by' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
