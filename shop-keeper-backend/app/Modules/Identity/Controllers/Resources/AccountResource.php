<?php

namespace App\Modules\Identity\Controllers\Resources;

use App\Modules\Shops\Actions\FindShopOfOwner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'two_factor_enabled' => $this->hasTwoFactor(),
            'shop' => app(FindShopOfOwner::class)->handle($this->id),
        ];
    }
}
