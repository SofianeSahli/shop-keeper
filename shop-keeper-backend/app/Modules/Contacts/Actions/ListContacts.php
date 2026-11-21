<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactRole;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListContacts
{
    public function __construct(private AttachContactBalances $attachContactBalances) {}

    public function handle(int $shopId, ?ContactRole $role, int $pageSize): CursorPaginator
    {
        $page = Contact::query()
            ->ofShop($shopId)
            ->withRole($role)
            ->orderBy('compared_name')
            ->orderBy('identity_key')
            ->cursorPaginate($pageSize)
            ->withQueryString();

        $contacts = $page->getCollection();
        $this->attachContactBalances->handle($shopId, $contacts);

        return $page;
    }
}
