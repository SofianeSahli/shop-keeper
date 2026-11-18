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
        $name = $request->validated('name');
        $email = $request->validated('email');
        $password = $request->validated('password');
        $termsVersion = $request->validated('terms_version');
        $phone = $request->normalizedPhone();
        $registerWithEmail->handle($name, $email, $password, $termsVersion, $phone);

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function confirm(ConfirmEmailRequest $request, ConfirmEmail $confirmEmail): SessionResource
    {
        $email = $request->validated('email');
        $code = $request->validated('code');
        $deviceId = $request->validated('device_id');
        $signIn = $confirmEmail->handle($email, $code, $deviceId);

        return new SessionResource($signIn);
    }

    public function resend(EmailAddressRequest $request, ResendEmailCode $resendEmailCode): Response
    {
        $email = $request->validated('email');
        $resendEmailCode->handle($email);

        return response()->noContent(Response::HTTP_ACCEPTED);
    }
}
