<?php

namespace App\Modules\Identity\Controllers\Requests;

final class RecoverPinRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'pin' => $this->newPinRules(),
            'id_token' => ['nullable', 'required_without:password', 'string'],
            'password' => ['nullable', 'string'],
        ];
    }
}
