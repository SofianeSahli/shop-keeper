<?php

namespace App\Modules\Contacts\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at->toIso8601ZuluString();
        $updatedAt = $this->updated_at->toIso8601ZuluString();

        return [
            'id' => $this->uuid(),
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'note' => $this->note,
            'is_supplier' => $this->is_supplier,
            'is_customer' => $this->is_customer,
            'balance' => $this->balance,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }
}
