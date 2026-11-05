<?php

namespace App\Modules\Identity\Controllers\Requests;

final class RegisterRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => $this->emailRules(),
            'password' => $this->newPasswordRules(),
            'terms_version' => ['required', ...$this->currentTermsRule()],
            'phone' => $this->phoneRules(),
        ];
    }
}
