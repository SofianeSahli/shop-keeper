<?php

namespace App\Modules\Shops\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'business_type' => $this->business_type,
            'currency' => $this->currency,
            'city' => $this->city,
            'address' => $this->address,
            'owner_id' => $this->owner_id,
        ];
    }
}
