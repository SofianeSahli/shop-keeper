<?php

namespace App\Modules\Contacts\Controllers\Requests;

use App\Modules\Contacts\Support\ContactChanges;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateContactRequest extends FormRequest
{
    private const NAME_PRESENCE = ['sometimes', 'required'];

    private const ROLES = ['is_supplier', 'is_customer'];

    private const OPTIONAL_TEXTS = ['address', 'note'];

    public function rules(): array
    {
        $fieldRules = ContactFieldRules::rules(self::NAME_PRESENCE);

        return [
            ...$fieldRules,
            'id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        $fieldMessages = ContactFieldRules::messages();

        return [
            ...$fieldMessages,
            'id.prohibited' => 'id.prohibited',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $hasChanges = $this->hasAny(ContactFieldRules::CHANGEABLE_FIELDS);

            if (! $hasChanges) {
                $validator->errors()->add('fields', 'fields.required');
            }
        }];
    }

    public function changes(): ContactChanges
    {
        $name = $this->validated('name');
        $validated = $this->validated();
        $otherFields = [];

        if ($this->has('phone')) {
            $otherFields['phone'] = ContactFieldRules::normalizedPhone($validated['phone']);
        }

        foreach (self::OPTIONAL_TEXTS as $field) {
            if ($this->has($field)) {
                $otherFields[$field] = $validated[$field];
            }
        }

        foreach (self::ROLES as $role) {
            if ($this->has($role)) {
                $otherFields[$role] = (bool) $validated[$role];
            }
        }

        return new ContactChanges($name, $otherFields);
    }
}
