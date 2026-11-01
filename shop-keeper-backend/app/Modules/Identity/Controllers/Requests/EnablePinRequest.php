<?php

namespace App\Modules\Identity\Controllers\Requests;

final class EnablePinRequest extends IdentityRequest
{
    public function rules(): array
    {
        return ['pin' => $this->newPinRules()];
    }
}
