<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\AuthTestMode;

final class GenerateEmailCode
{
    private const TEST_MODE_CODE = '000000';

    private const CODE_LENGTH = 6;

    private const LARGEST_CODE = 999999;

    public function handle(): string
    {
        if (AuthTestMode::isOn()) {
            return self::TEST_MODE_CODE;
        }

        return str_pad((string) random_int(0, self::LARGEST_CODE), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
