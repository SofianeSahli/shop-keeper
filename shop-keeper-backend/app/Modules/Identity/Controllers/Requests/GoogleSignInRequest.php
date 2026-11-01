<?php

namespace App\Modules\Identity\Controllers\Requests;

final class GoogleSignInRequest extends IdentityRequest
{
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'device_id' => $this->deviceIdRules(),
            'terms_version' => ['nullable', ...$this->currentTermsRule()],
            'phone' => $this->phoneRules(),
        ];
    }
}
