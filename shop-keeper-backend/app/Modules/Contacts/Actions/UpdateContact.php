<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Support\ContactChanges;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateContact
{
    public function __construct(
        private ContactNameRules $nameRules,
        private FindContactOfShop $findContactOfShop,
    ) {}

    public function handle(Contact $contact, ContactChanges $changes): Contact
    {
        $contactId = $contact->uuid();

        DB::transaction(function () use ($contactId, $changes): void {
            $locked = Contact::query()->whereUuid($contactId)->lockForUpdate()->firstOrFail();

            if ($changes->name !== null) {
                $this->checkNewName($locked, $changes->name);
                $locked->name = $changes->name;
            }

            $locked->fill($changes->otherFields);
            $this->save($locked);
        });

        return $this->findContactOfShop->handle($contact->shop_id, $contactId);
    }

    private function checkNewName(Contact $contact, string $newName): void
    {
        $comparedName = NameComparison::compared($newName);

        if ($comparedName === $contact->compared_name) {
            return;
        }

        $contactId = $contact->uuid();
        $this->nameRules->refuseIfNameTaken($contact->shop_id, $newName, exceptContactId: $contactId);
    }

    private function save(Contact $contact): void
    {
        try {
            $contact->save();
        } catch (UniqueConstraintViolationException $lostRace) {
            $contactId = $contact->uuid();
            $this->nameRules->refuseIfNameTaken($contact->shop_id, $contact->name, exceptContactId: $contactId, readLatest: true);

            throw $lostRace;
        }
    }
}
