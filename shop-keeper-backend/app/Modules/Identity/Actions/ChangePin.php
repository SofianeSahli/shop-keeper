<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\Hash;

final class ChangePin
{
    public function __construct(private readonly CheckPin $checkPin) {}

    public function handle(User $user, string $currentPin, string $newPin): void
    {
        if (! $user->hasPin()) {
            throw new ApiException('pin.not_enabled', 409);
        }

        $this->checkPin->handle($user, $currentPin);

        $user->forceFill(['pin_hash' => Hash::make($newPin)])->save();
    }
}
