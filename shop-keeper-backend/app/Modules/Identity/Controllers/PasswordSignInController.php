<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\SignInWithPassword;
use App\Modules\Identity\Controllers\Requests\PasswordSignInRequest;
use App\Modules\Identity\Controllers\Resources\SessionResource;

final class PasswordSignInController
{
    public function __invoke(PasswordSignInRequest $request, SignInWithPassword $signInWithPassword): SessionResource
    {
        $email = $request->validated('email');
        $password = $request->validated('password');
        $deviceId = $request->validated('device_id');
        $signIn = $signInWithPassword->handle($email, $password, $deviceId);

        return new SessionResource($signIn);
    }
}
