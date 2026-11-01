<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\ConfirmPin;
use App\Modules\Identity\Controllers\Requests\ConfirmPinRequest;
use App\Modules\Identity\Controllers\Resources\SessionResource;

final class PinSignInController
{
    public function __invoke(ConfirmPinRequest $request, ConfirmPin $confirmPin): SessionResource
    {
        return new SessionResource($confirmPin->handle(
            $request->validated('pending_sign_in'),
            $request->validated('pin'),
        ));
    }
}
