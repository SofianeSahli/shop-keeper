<?php

namespace App\Modules\Transactions\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

#[Fillable(['id', 'shop_id', 'kind', 'contact_id', 'transaction_date', 'reference', 'total', 'line_count', 'created_by'])]
class Transaction extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    public function scopeOfShop(Builder $query, int $shopId): void
    {
        $query->where('transactions.shop_id', $shopId);
    }

    public function scopeOfKind(Builder $query, ?TransactionKind $kind): void
    {
        if ($kind === null) {
            return;
        }

        $query->where('kind', $kind->value);
    }

    public function scopeOfContact(Builder $query, ?string $contactId): void
    {
        if ($contactId === null) {
            return;
        }

        $contactBytes = BinaryUuid::toBytes($contactId);
        $query->where('contact_id', $contactBytes);
    }

    public function scopeNotCancelled(Builder $query): void
    {
        $query->whereNotExists(function (QueryBuilder $cancellations): void {
            $cancellations->selectRaw('1')
                ->from('transaction_cancellations')
                ->whereColumn('transaction_cancellations.transaction_id', 'transactions.id');
        });
    }

    public function scopeWithCancellation(Builder $query): void
    {
        $cancellation = fn (string $column) => TransactionCancellation::query()
            ->select($column)
            ->whereColumn('transaction_cancellations.transaction_id', 'transactions.id');

        $cancellationReason = $cancellation('reason');
        $cancelledBy = $cancellation('cancelled_by');
        $cancelledAt = $cancellation('created_at');

        $query->select('transactions.*')->addSelect([
            'cancellation_reason' => $cancellationReason,
            'cancelled_by' => $cancelledBy,
            'cancelled_at' => $cancelledAt,
        ]);
    }

    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('transaction_date')->orderByDesc('sort_key');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * @return Collection<int, TransactionLine>
     */
    public function orderedLines(): Collection
    {
        $transactionBytes = $this->getKey();

        return TransactionLine::query()->where('transaction_id', $transactionBytes)->orderBy('position')->get();
    }

    protected function casts(): array
    {
        return [
            'kind' => TransactionKind::class,
            'contact_id' => BinaryUuid::class,
            'total' => 'integer',
            'line_count' => 'integer',
            'created_by' => 'integer',
            'cancelled_by' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
