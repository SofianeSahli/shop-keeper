<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Contacts\Models\Contact;
use App\Shared\Errors\ApiException;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

final class ContactNameRules
{
    public function refuseIfNameTaken(int $shopId, string $name, ?string $exceptContactId = null, bool $readLatest = false): void
    {
        $identityKey = NameComparison::identityKey($name);
        $existing = Contact::query()
            ->ofShop($shopId)
            ->where('identity_key', $identityKey)
            ->when($exceptContactId !== null, fn (Builder $query) => $query->whereNot(fn (Builder $excluded) => $excluded->whereUuid($exceptContactId)))
            ->when($readLatest, fn (Builder $query) => $query->lockForUpdate())
            ->first();

        if ($existing !== null) {
            $existingContactId = $existing->uuid();

            throw new ApiException('contact.name_taken', Response::HTTP_CONFLICT, details: ['existing_contact_id' => $existingContactId]);
        }
    }
}
