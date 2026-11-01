<?php

namespace App\Modules\Shops\Controllers\Requests;

use App\Modules\Shops\Models\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateShopRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', Rule::enum(BusinessType::class)],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'name.required',
            'name.string' => 'name.invalid',
            'name.max' => 'name.too_long',
            'business_type.required' => 'business_type.required',
            'business_type.Illuminate\Validation\Rules\Enum' => 'business_type.invalid',
            'city.string' => 'city.invalid',
            'city.max' => 'city.too_long',
            'address.string' => 'address.invalid',
            'address.max' => 'address.too_long',
        ];
    }
}
