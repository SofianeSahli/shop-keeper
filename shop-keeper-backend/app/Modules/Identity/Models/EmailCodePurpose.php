<?php

namespace App\Modules\Identity\Models;

enum EmailCodePurpose: string
{
    case EmailConfirmation = 'email_confirmation';
    case PasswordReset = 'password_reset';
}
