<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\ConfirmSignInCode;
use App\Modules\Identity\Actions\ResendSignInCode;
use App\Modules\Identity\Controllers\Requests\ConfirmSignInCodeRequest;
use App\Modules\Identity\Controllers\Requests\ResendSignInCodeRequest;
use App\Modules\Identity\Controllers\Resources\SessionResource;
use Illuminate\Http\Response;

final class TwoFactorSignInController
{
    public function confirm(ConfirmSignInCodeRequest $request, ConfirmSignInCode $confirmSignInCode): SessionResource
    {
        return new SessionResource($confirmSignInCode->handle(
            $request->validated('pending_sign_in'),
            $request->validated('code'),
        ));
    }

    public function resend(ResendSignInCodeRequest $request, ResendSignInCode $resendSignInCode): Response
    {
        $resendSignInCode->handle($request->validated('pending_sign_in'));

        return response()->noContent(Response::HTTP_ACCEPTED);
    }
}
