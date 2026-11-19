<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\SignOutDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LogoutController
{
    public function __invoke(Request $request, SignOutDevice $signOutDevice): Response
    {
        $user = $request->user();
        $signOutDevice->handle($user);

        return response()->noContent();
    }
}
