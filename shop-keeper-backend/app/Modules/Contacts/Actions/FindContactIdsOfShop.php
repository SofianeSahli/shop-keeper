<?php

namespace App\Modules\Contacts\Actions;

final class FindContactIdsOfShop
{
    public function __construct(private FindContactNamesOfShop $findContactNamesOfShop) {}

    /**
     * @param  list<string>  $contactIds
     * @return list<string>
     */
    public function handle(int $shopId, array $contactIds): array
    {
        $namesById = $this->findContactNamesOfShop->handle($shopId, $contactIds);

        return array_keys($namesById);
    }
}
