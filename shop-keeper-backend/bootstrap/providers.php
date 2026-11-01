<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Shops\ShopsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    ShopsServiceProvider::class,
];
