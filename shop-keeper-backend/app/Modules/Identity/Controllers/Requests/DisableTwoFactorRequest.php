<?php

namespace App\Modules\Identity\Controllers\Requests;

final class DisableTwoFactorRequest extends IdentityRequest
{
    public function rules(): array
    {
        return ['code' => $this->codeRules()];
    }
}
