<?php

namespace App\Modules\Transactions\Contracts;

interface TransactionPayments
{
    /**
     * @param  list<string>  $transactionIds
     * @return array<string, int>
     */
    public function amountsPaid(int $shopId, array $transactionIds): array;

    public function hasActivePayments(string $transactionId): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentsOf(int $shopId, string $transactionId): array;
}
