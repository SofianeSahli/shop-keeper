<?php

namespace App\Modules\Identity\Controllers\Requests;

final class ConfirmSignInCodeRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'pending_sign_in' => $this->pendingSignInRules(),
            'code' => $this->codeRules(),
        ];
    }
}
