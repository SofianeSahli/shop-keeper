<?php

namespace App\Modules\Purchasing\Controllers\Requests;

use App\Modules\Identity\Rules\InternationalPhone;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Support\SupplierChanges;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateSupplierRequest extends FormRequest
{
    private const CHANGEABLE_FIELDS = ['name', 'phone'];

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.Supplier::NAME_MAX_LENGTH],
            'phone' => ['sometimes', 'nullable', new InternationalPhone],
            'id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'name.required',
            'name.string' => 'name.invalid',
            'name.max' => 'name.too_long',
            'id.prohibited' => 'id.prohibited',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(self::CHANGEABLE_FIELDS)) {
                $validator->errors()->add('fields', 'fields.required');
            }
        }];
    }

    public function changes(): SupplierChanges
    {
        $name = $this->validated('name');
        $changesPhone = $this->has('phone');
        $phone = $this->validated('phone');
        $normalizedPhone = $phone === null ? null : InternationalPhone::normalize($phone);

        return new SupplierChanges(
            name: $name,
            changesPhone: $changesPhone,
            phone: $normalizedPhone,
        );
    }
}
