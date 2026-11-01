<?php

namespace App\Modules\Identity\Controllers\Requests;

final class ConfirmPinRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'pending_sign_in' => ['required', 'string'],
            'pin' => $this->enteredPinRules(),
        ];
    }
}
