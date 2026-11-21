<?php

namespace App\Modules\Contacts\Policies;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Identity\Models\User;
use App\Shared\CurrentShop;

final class ContactPolicy
{
    public function __construct(private CurrentShop $currentShop) {}

    public function view(User $user, Contact $contact): bool
    {
        return $contact->shop_id === $this->currentShop->shopId;
    }

    public function update(User $user, Contact $contact): bool
    {
        return $contact->shop_id === $this->currentShop->shopId;
    }
}
