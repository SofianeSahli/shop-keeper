<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Contacts\ContactsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Shops\ShopsServiceProvider;
use App\Modules\Transactions\TransactionsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    ShopsServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    ContactsServiceProvider::class,
    TransactionsServiceProvider::class,
    PaymentsServiceProvider::class,
];
