<?php

namespace App\Modules\Identity\Controllers\Requests;

final class EmailAddressRequest extends IdentityRequest
{
    public function rules(): array
    {
        return ['email' => $this->emailRules()];
    }
}
