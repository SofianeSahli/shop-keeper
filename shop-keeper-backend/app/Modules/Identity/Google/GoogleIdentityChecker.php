<?php

namespace App\Modules\Identity\Google;

interface GoogleIdentityChecker
{
    public function verify(string $idToken): GoogleIdentity;
}
