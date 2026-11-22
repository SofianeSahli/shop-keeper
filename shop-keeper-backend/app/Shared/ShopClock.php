<?php

namespace App\Shared;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

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

    public function dateOf(CarbonInterface $moment): string
    {
        $timeZone = config('shop_keeper.shop_time_zone');
        $momentInShop = CarbonImmutable::instance($moment)->setTimezone($timeZone);

        return $momentInShop->toDateString();
    }
}
