<?php

namespace App\Modules\Identity\Controllers\Requests;

final class ConfirmEmailRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->emailRules(),
            'code' => $this->codeRules(),
            'device_id' => $this->deviceIdRules(),
        ];
    }
}
