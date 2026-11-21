<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Contacts\Models\Contact;
use App\Shared\Database\BinaryUuid;
use Illuminate\Support\Str;

final class FindContactNamesOfShop
{
    /**
     * @param  list<?string>  $contactIds
     * @return array<string, string>
     */
    public function handle(int $shopId, array $contactIds): array
    {
        $validIds = array_filter($contactIds, fn (?string $contactId): bool => $contactId !== null && Str::isUuid($contactId));

        if ($validIds === []) {
            return [];
        }

        $uniqueIds = array_unique($validIds);
        $contactBytes = array_map(fn (string $contactId): string => BinaryUuid::toBytes($contactId), $uniqueIds);
        $contacts = Contact::query()
            ->ofShop($shopId)
            ->whereIn('id', $contactBytes)
            ->get(['id', 'name']);

        return $contacts->mapWithKeys(fn (Contact $contact): array => [$contact->uuid() => $contact->name])->all();
    }
}
