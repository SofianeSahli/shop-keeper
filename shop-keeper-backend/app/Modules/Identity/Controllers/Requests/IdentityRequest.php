<?php

namespace App\Modules\Identity\Controllers\Requests;

use App\Modules\Identity\Rules\InternationalPhone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class IdentityRequest extends FormRequest
{
    private const PASSWORD_MAX_BYTES = 72;

    private const FIELD_CODES = [
        'id_token.required' => 'id_token.required',
        'id_token.required_without' => 'id_token.required',
        'id_token.string' => 'id_token.invalid',
        'device_id.required' => 'device_id.required',
        'device_id.string' => 'device_id.invalid',
        'device_id.uuid' => 'device_id.invalid',
        'terms_version.required' => 'terms_version.required',
        'terms_version.string' => 'terms_version.outdated',
        'terms_version.in' => 'terms_version.outdated',
        'name.required' => 'name.required',
        'name.string' => 'name.invalid',
        'name.max' => 'name.too_long',
        'email.required' => 'email.required',
        'email.string' => 'email.invalid',
        'email.email' => 'email.invalid',
        'email.max' => 'email.invalid',
        'password.required' => 'password.required',
        'password.string' => 'password.invalid',
        'password.min' => 'password.too_short',
        'code.required' => 'code.required',
        'code.string' => 'code.format',
        'code.digits' => 'code.format',
        'pending_sign_in.required' => 'pending_sign_in.required',
        'pending_sign_in.string' => 'pending_sign_in.invalid',
    ];

    public function messages(): array
    {
        return self::FIELD_CODES;
    }

    public function normalizedPhone(): ?string
    {
        $phone = $this->validated('phone');

        return $phone === null ? null : InternationalPhone::normalize($phone);
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower($email)]);
        }
    }

    protected function deviceIdRules(): array
    {
        return ['required', 'string', 'uuid'];
    }

    protected function emailRules(): array
    {
        return ['required', 'string', 'email', 'max:255'];
    }

    protected function codeRules(): array
    {
        return ['required', 'string', 'digits:6'];
    }

    protected function pendingSignInRules(): array
    {
        return ['required', 'string'];
    }

    protected function phoneRules(): array
    {
        return ['nullable', new InternationalPhone];
    }

    protected function currentTermsRule(): array
    {
        return ['string', Rule::in([config('shop_keeper.terms_version')])];
    }

    protected function newPasswordRules(): array
    {
        return ['required', 'string', 'min:8', function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && strlen($value) > self::PASSWORD_MAX_BYTES) {
                $fail('password.too_long');
            }
        }];
    }
}
