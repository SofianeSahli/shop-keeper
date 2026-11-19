<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\RequestPasswordReset;
use App\Modules\Identity\Actions\ResetPassword;
use App\Modules\Identity\Controllers\Requests\EmailAddressRequest;
use App\Modules\Identity\Controllers\Requests\ResetPasswordRequest;
use Illuminate\Http\Response;

final class PasswordResetController
{
    public function forgot(EmailAddressRequest $request, RequestPasswordReset $requestPasswordReset): Response
    {
        $email = $request->validated('email');
        $requestPasswordReset->handle($email);

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $resetPassword): Response
    {
        $email = $request->validated('email');
        $code = $request->validated('code');
        $password = $request->validated('password');
        $resetPassword->handle($email, $code, $password);

        return response()->noContent();
    }
}
