<?php

namespace App\Modules\Transactions\Support;

use App\Modules\Inventory\Contracts\PurchaseLineSources;
use App\Modules\Inventory\Support\PurchaseLineSource;
use App\Modules\Transactions\Models\TransactionLine;
use App\Shared\Database\BinaryUuid;
use App\Shared\ShopClock;
use Illuminate\Support\Carbon;

final class DescribePurchaseLines implements PurchaseLineSources
{
    public function __construct(private ShopClock $shopClock) {}

    public function describe(int $shopId, array $lineIds): array
    {
        $lineBytes = array_map(fn (string $lineId): string => BinaryUuid::toBytes($lineId), $lineIds);
        $rows = TransactionLine::query()
            ->toBase()
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->leftJoin('transaction_cancellations', 'transaction_cancellations.transaction_id', '=', 'transactions.id')
            ->where('transactions.shop_id', $shopId)
            ->whereIn('transaction_lines.id', $lineBytes)
            ->select([
                'transaction_lines.id as line_id',
                'transactions.id as transaction_id',
                'transactions.transaction_date',
                'transactions.created_by',
                'transaction_cancellations.cancelled_by',
                'transaction_cancellations.created_at as cancelled_at',
            ])
            ->get();

        $sources = [];

        foreach ($rows as $row) {
            $lineId = BinaryUuid::fromBytes($row->line_id);
            $sources[$lineId] = $this->source($row);
        }

        return $sources;
    }

    private function source(object $row): PurchaseLineSource
    {
        $transactionId = BinaryUuid::fromBytes($row->transaction_id);
        $cancelledOn = null;

        if ($row->cancelled_at !== null) {
            $cancelledAt = Carbon::parse($row->cancelled_at);
            $cancelledOn = $this->shopClock->dateOf($cancelledAt);
        }

        $cancelledBy = $row->cancelled_by === null ? null : (int) $row->cancelled_by;

        return new PurchaseLineSource(
            transactionId: $transactionId,
            transactionDate: $row->transaction_date,
            recordedBy: (int) $row->created_by,
            cancelledBy: $cancelledBy,
            cancelledOn: $cancelledOn,
        );
    }
}
