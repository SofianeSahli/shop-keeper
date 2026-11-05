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
        $enableTwoFactor->handle($request->user());

        return response()->noContent();
    }

    public function sendDisableCode(Request $request, SendTwoFactorDisableCode $sendTwoFactorDisableCode): Response
    {
        $sendTwoFactorDisableCode->handle($request->user());

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function destroy(DisableTwoFactorRequest $request, DisableTwoFactor $disableTwoFactor): Response
    {
        $disableTwoFactor->handle($request->user(), $request->validated('code'));

        return response()->noContent();
    }
}
