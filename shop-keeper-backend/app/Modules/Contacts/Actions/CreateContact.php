<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Support\NewContact;
use App\Shared\Records\Created;
use App\Shared\Records\RecordWithSameId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class CreateContact
{
    public function __construct(
        private ContactNameRules $nameRules,
        private FindContactOfShop $findContactOfShop,
    ) {}

    public function handle(NewContact $new): Created
    {
        $wasCreated = DB::transaction(function () use ($new): bool {
            $contactQuery = Contact::query();
            $contactWithSameId = RecordWithSameId::find($contactQuery, $new->id, $new->shopId);

            if ($contactWithSameId !== null) {
                return false;
            }

            $this->nameRules->refuseIfNameTaken($new->shopId, $new->name);

            return $this->insert($new);
        });

        $contact = $this->findContactOfShop->handle($new->shopId, $new->id);

        return new Created($contact, $wasCreated);
    }

    private function insert(NewContact $new): bool
    {
        try {
            Contact::query()->create([
                'id' => $new->id,
                'shop_id' => $new->shopId,
                'name' => $new->name,
                'phone' => $new->phone,
                'address' => $new->address,
                'note' => $new->note,
                'is_supplier' => $new->isSupplier,
                'is_customer' => $new->isCustomer,
            ]);
        } catch (UniqueConstraintViolationException $lostRace) {
            $contactQuery = Contact::query();
            $winner = RecordWithSameId::find($contactQuery, $new->id, $new->shopId, readLatest: true);

            if ($winner !== null) {
                return false;
            }

            $this->nameRules->refuseIfNameTaken($new->shopId, $new->name, readLatest: true);

            throw $lostRace;
        }

        return true;
    }
}
