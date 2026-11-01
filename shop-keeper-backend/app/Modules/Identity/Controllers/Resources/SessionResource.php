<?php

namespace App\Modules\Identity\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource->pendingSignIn !== null) {
            return [
                'status' => 'pin_required',
                'pending_sign_in' => $this->resource->pendingSignIn,
                'expires_in_seconds' => $this->resource->expiresInSeconds,
            ];
        }

        return [
            'status' => 'signed_in',
            'token' => $this->resource->token,
            'account' => new AccountResource($this->resource->user),
        ];
    }
}
