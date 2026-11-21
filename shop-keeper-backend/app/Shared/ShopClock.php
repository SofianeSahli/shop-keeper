<?php

namespace App\Shared;

use Carbon\CarbonImmutable;

final class ShopClock
{
    public function today(): CarbonImmutable
    {
        $timeZone = config('shop_keeper.shop_time_zone');

        return CarbonImmutable::now($timeZone)->startOfDay();
    }

    public function todayAsDate(): string
    {
        return $this->today()->toDateString();
    }
}
