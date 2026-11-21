<?php

namespace App\Modules\Transactions\Actions;

use App\Modules\Transactions\Models\Transaction;
use App\Modules\Transactions\Models\TransactionKind;
use App\Shared\Database\BinaryUuid;

final class SumTransactionTotalsByContact
{
    /**
     * @param  list<string>  $contactIds
     * @return array<string, int>
     */
    public function handle(int $shopId, TransactionKind $kind, array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        $contactBytes = array_map(fn (string $contactId): string => BinaryUuid::toBytes($contactId), $contactIds);
        $totals = Transaction::query()
            ->ofShop($shopId)
            ->ofKind($kind)
            ->whereIn('contact_id', $contactBytes)
            ->notCancelled()
            ->groupBy('contact_id')
            ->selectRaw('contact_id, sum(total) as contact_total')
            ->get();

        return $totals->mapWithKeys(fn (Transaction $total): array => [$total->contact_id => (int) $total->contact_total])->all();
    }
}
