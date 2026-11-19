<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\DisableTwoFactor;
use App\Modules\Identity\Actions\EnableTwoFactor;
use App\Modules\Identity\Actions\SendTwoFactorDisableCode;
use App\Modules\Identity\Controllers\Requests\DisableTwoFactorRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TwoFactorController
{
    public function store(Request $request, EnableTwoFactor $enableTwoFactor): Response
    {
        $user = $request->user();
        $enableTwoFactor->handle($user);

        return response()->noContent();
    }

    public function sendDisableCode(Request $request, SendTwoFactorDisableCode $sendTwoFactorDisableCode): Response
    {
        $user = $request->user();
        $sendTwoFactorDisableCode->handle($user);

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function destroy(DisableTwoFactorRequest $request, DisableTwoFactor $disableTwoFactor): Response
    {
        $user = $request->user();
        $code = $request->validated('code');
        $disableTwoFactor->handle($user, $code);

        return response()->noContent();
    }
}
