<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\SignInWithGoogle;
use App\Modules\Identity\Controllers\Requests\GoogleSignInRequest;
use App\Modules\Identity\Controllers\Resources\SessionResource;

final class GoogleSignInController
{
    public function __invoke(GoogleSignInRequest $request, SignInWithGoogle $signInWithGoogle): SessionResource
    {
        $idToken = $request->validated('id_token');
        $deviceId = $request->validated('device_id');
        $termsVersion = $request->validated('terms_version');
        $phone = $request->normalizedPhone();
        $signIn = $signInWithGoogle->handle($idToken, $deviceId, $termsVersion, $phone);

        return new SessionResource($signIn);
    }
}
