<?php

namespace App\Modules\Identity\Controllers\Requests;

final class ResendSignInCodeRequest extends IdentityRequest
{
    public function rules(): array
    {
        return ['pending_sign_in' => $this->pendingSignInRules()];
    }
}
