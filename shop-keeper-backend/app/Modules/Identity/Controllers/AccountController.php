<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Controllers\Resources\AccountResource;
use Illuminate\Http\Request;

final class AccountController
{
    public function show(Request $request): AccountResource
    {
        $user = $request->user();

        return new AccountResource($user);
    }
}
