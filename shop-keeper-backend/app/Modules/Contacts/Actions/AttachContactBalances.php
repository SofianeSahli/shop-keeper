<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Contacts\Contracts\ContactBalances;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Support\Collection;

final class AttachContactBalances
{
    public function __construct(private ContactBalances $contactBalances) {}

    /**
     * @param  Collection<int, Contact>  $contacts
     */
    public function handle(int $shopId, Collection $contacts): void
    {
        $contactIds = $contacts->map(fn (Contact $contact): string => $contact->uuid())->all();
        $balanceById = $this->contactBalances->balances($shopId, $contactIds);

        foreach ($contacts as $contact) {
            $contactId = $contact->uuid();
            $contact->balance = $balanceById[$contactId] ?? 0;
        }
    }
}
