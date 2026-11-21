<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Contacts\Models\Contact;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class FindContactOfShop
{
    public function __construct(private AttachContactBalances $attachContactBalances) {}

    public function handle(int $shopId, string $contactId): Contact
    {
        $contact = Contact::query()
            ->ofShop($shopId)
            ->whereUuid($contactId)
            ->first() ?? throw new ApiException('contact.not_found', Response::HTTP_NOT_FOUND);

        $contacts = collect([$contact]);
        $this->attachContactBalances->handle($shopId, $contacts);

        return $contact;
    }
}
