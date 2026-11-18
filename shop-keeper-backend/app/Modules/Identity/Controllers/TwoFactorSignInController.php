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
        $pendingToken = $request->validated('pending_sign_in');
        $code = $request->validated('code');
        $signIn = $confirmSignInCode->handle($pendingToken, $code);

        return new SessionResource($signIn);
    }

    public function resend(ResendSignInCodeRequest $request, ResendSignInCode $resendSignInCode): Response
    {
        $pendingToken = $request->validated('pending_sign_in');
        $resendSignInCode->handle($pendingToken);

        return response()->noContent(Response::HTTP_ACCEPTED);
    }
}
