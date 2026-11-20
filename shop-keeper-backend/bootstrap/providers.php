<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Purchasing\PurchasingServiceProvider;
use App\Modules\Shops\ShopsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    ShopsServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    PurchasingServiceProvider::class,
];
