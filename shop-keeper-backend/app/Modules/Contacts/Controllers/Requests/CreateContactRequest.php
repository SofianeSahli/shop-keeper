<?php

namespace App\Modules\Contacts\Controllers\Requests;

use App\Modules\Contacts\Support\NewContact;
use Illuminate\Foundation\Http\FormRequest;

final class CreateContactRequest extends FormRequest
{
    private const NAME_PRESENCE = ['required'];

    public function rules(): array
    {
        $fieldRules = ContactFieldRules::rules(self::NAME_PRESENCE);

        return [
            'id' => ['required', 'string', 'uuid'],
            ...$fieldRules,
        ];
    }

    public function messages(): array
    {
        $fieldMessages = ContactFieldRules::messages();

        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            ...$fieldMessages,
        ];
    }

    public function newContact(int $shopId): NewContact
    {
        $id = $this->validated('id');
        $lowercaseId = strtolower($id);
        $name = $this->validated('name');
        $phone = $this->validated('phone');
        $normalizedPhone = ContactFieldRules::normalizedPhone($phone);
        $address = $this->validated('address');
        $note = $this->validated('note');
        $isSupplier = $this->validated('is_supplier', false);
        $isCustomer = $this->validated('is_customer', false);

        return new NewContact(
            id: $lowercaseId,
            shopId: $shopId,
            name: $name,
            phone: $normalizedPhone,
            address: $address,
            note: $note,
            isSupplier: (bool) $isSupplier,
            isCustomer: (bool) $isCustomer,
        );
    }
}
