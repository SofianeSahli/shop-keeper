<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;

final class DisablePin
{
    public function __construct(private readonly CheckPin $checkPin) {}

    public function handle(User $user, string $currentPin): void
    {
        if (! $user->hasPin()) {
            throw new ApiException('pin.not_enabled', 409);
        }

        $this->checkPin->handle($user, $currentPin);

        DB::transaction(function () use ($user): void {
            $user->forceFill(['pin_hash' => null])->save();

            PendingSignIn::query()->where('user_id', $user->id)->delete();
        });
    }
}
