<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\SignInWithGoogle;
use App\Modules\Identity\Controllers\Requests\GoogleSignInRequest;
use App\Modules\Identity\Controllers\Resources\SessionResource;

final class GoogleSignInController
{
    public function __invoke(GoogleSignInRequest $request, SignInWithGoogle $signInWithGoogle): SessionResource
    {
        return new SessionResource($signInWithGoogle->handle(
            $request->validated('id_token'),
            $request->validated('device_id'),
            $request->validated('terms_version'),
            $request->normalizedPhone(),
        ));
    }
}
