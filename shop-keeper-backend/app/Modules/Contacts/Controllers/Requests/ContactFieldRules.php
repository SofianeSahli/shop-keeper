<?php

namespace App\Modules\Contacts\Controllers\Requests;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Identity\Rules\InternationalPhone;

final class ContactFieldRules
{
    public const CHANGEABLE_FIELDS = ['name', 'phone', 'address', 'note', 'is_supplier', 'is_customer'];

    /**
     * @param  list<string>  $namePresence
     */
    public static function rules(array $namePresence): array
    {
        $phone = new InternationalPhone;
        $optionalText = ['nullable', 'string', 'max:'.Contact::TEXT_MAX_LENGTH];

        return [
            'name' => [...$namePresence, 'string', 'max:'.Contact::NAME_MAX_LENGTH],
            'phone' => ['nullable', $phone],
            'address' => $optionalText,
            'note' => $optionalText,
            'is_supplier' => ['boolean'],
            'is_customer' => ['boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'name.required',
            'name.string' => 'name.invalid',
            'name.max' => 'name.too_long',
            'address.string' => 'address.invalid',
            'address.max' => 'address.too_long',
            'note.string' => 'note.invalid',
            'note.max' => 'note.too_long',
            'is_supplier.boolean' => 'is_supplier.invalid',
            'is_customer.boolean' => 'is_customer.invalid',
        ];
    }

    public static function normalizedPhone(?string $phone): ?string
    {
        return $phone === null ? null : InternationalPhone::normalize($phone);
    }
}
