<?php

namespace App\Modules\Identity;

final class AuthTestMode
{
    public static function isOn(): bool
    {
        return config('shop_keeper.auth_test_mode') === true && ! app()->isProduction();
    }
}
