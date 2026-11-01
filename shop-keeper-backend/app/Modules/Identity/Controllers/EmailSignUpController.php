<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\ConfirmEmail;
use App\Modules\Identity\Actions\RegisterWithEmail;
use App\Modules\Identity\Actions\ResendEmailCode;
use App\Modules\Identity\Controllers\Requests\ConfirmEmailRequest;
use App\Modules\Identity\Controllers\Requests\EmailAddressRequest;
use App\Modules\Identity\Controllers\Requests\RegisterRequest;
use App\Modules\Identity\Controllers\Resources\SessionResource;
use Illuminate\Http\Response;

final class EmailSignUpController
{
    public function register(RegisterRequest $request, RegisterWithEmail $registerWithEmail): Response
    {
        $registerWithEmail->handle(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('terms_version'),
            $request->normalizedPhone(),
        );

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function confirm(ConfirmEmailRequest $request, ConfirmEmail $confirmEmail): SessionResource
    {
        return new SessionResource($confirmEmail->handle(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('device_id'),
        ));
    }

    public function resend(EmailAddressRequest $request, ResendEmailCode $resendEmailCode): Response
    {
        $resendEmailCode->handle($request->validated('email'));

        return response()->noContent(Response::HTTP_ACCEPTED);
    }
}
