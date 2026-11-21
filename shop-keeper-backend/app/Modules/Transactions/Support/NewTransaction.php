<?php

namespace App\Modules\Transactions\Support;

use App\Modules\Transactions\Models\TransactionKind;

final readonly class NewTransaction
{
    /**
     * @param  list<NewTransactionLine>  $lines
     */
    public function __construct(
        public string $id,
        public int $shopId,
        public TransactionKind $kind,
        public ?string $contactId,
        public string $transactionDate,
        public ?string $reference,
        public array $lines,
        public int $recordedBy,
    ) {}

    /**
     * @return list<string>
     */
    public function productIds(): array
    {
        return array_map(fn (NewTransactionLine $line): string => $line->productId, $this->lines);
    }

    public function total(): int
    {
        $amounts = array_map(fn (NewTransactionLine $line): int => $line->amount(), $this->lines);

        return array_sum($amounts);
    }
}
