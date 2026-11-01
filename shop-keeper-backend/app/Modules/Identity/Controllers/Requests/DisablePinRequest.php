<?php

namespace App\Modules\Identity\Controllers\Requests;

final class DisablePinRequest extends IdentityRequest
{
    public function rules(): array
    {
        return ['current_pin' => $this->enteredPinRules()];
    }
}
