<?php

namespace App\Modules\Identity\Controllers\Requests;

final class ChangePinRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'current_pin' => $this->enteredPinRules(),
            'pin' => $this->newPinRules(),
        ];
    }
}
