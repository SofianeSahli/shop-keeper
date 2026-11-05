<?php

namespace App\Modules\Identity\Controllers\Requests;

final class PasswordSignInRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->emailRules(),
            'password' => ['required', 'string'],
            'device_id' => $this->deviceIdRules(),
        ];
    }
}
