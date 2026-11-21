<?php

namespace App\Modules\Contacts\Controllers\Resources;

final class ContactSummary
{
    /**
     * @return array{id: string, name: ?string}|null
     */
    public static function of(?string $contactId, ?string $contactName): ?array
    {
        if ($contactId === null) {
            return null;
        }

        return ['id' => $contactId, 'name' => $contactName];
    }
}
