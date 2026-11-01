<?php

namespace App\Modules\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class SpendTimeOfSendingCode
{
    private const THROWAWAY_LENGTH = 6;

    public function handle(): void
    {
        Hash::make(Str::random(self::THROWAWAY_LENGTH));
    }
}
