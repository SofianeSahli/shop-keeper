<?php

namespace App\Modules\Purchasing\Controllers\Requests;

use App\Modules\Identity\Rules\InternationalPhone;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Support\NewSupplier;
use Illuminate\Foundation\Http\FormRequest;

final class CreateSupplierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:'.Supplier::NAME_MAX_LENGTH],
            'phone' => ['nullable', new InternationalPhone],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            'name.required' => 'name.required',
            'name.string' => 'name.invalid',
            'name.max' => 'name.too_long',
        ];
    }

    public function newSupplier(int $shopId): NewSupplier
    {
        $id = $this->validated('id');
        $lowercaseId = strtolower($id);
        $name = $this->validated('name');
        $phone = $this->validated('phone');
        $normalizedPhone = $phone === null ? null : InternationalPhone::normalize($phone);

        return new NewSupplier(
            id: $lowercaseId,
            shopId: $shopId,
            name: $name,
            phone: $normalizedPhone,
        );
    }
}
