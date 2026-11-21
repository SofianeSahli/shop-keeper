<?php

namespace App\Modules\Contacts\Contracts;

interface ContactBalances
{
    /**
     * @param  list<string>  $contactIds
     * @return array<string, int>
     */
    public function balances(int $shopId, array $contactIds): array;
}
