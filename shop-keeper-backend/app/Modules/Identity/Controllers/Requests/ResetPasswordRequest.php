<?php

namespace App\Modules\Identity\Controllers\Requests;

final class ResetPasswordRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->emailRules(),
            'code' => $this->codeRules(),
            'password' => $this->newPasswordRules(),
        ];
    }
}
