<?php

namespace App\Modules\Identity\Mail;

use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class EmailCodeMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $code,
        public readonly EmailCodePurpose $purpose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->purpose) {
            EmailCodePurpose::EmailConfirmation => 'Votre code de confirmation · رمز التأكيد',
            EmailCodePurpose::PasswordReset => 'Votre code de réinitialisation · رمز إعادة التعيين',
        });
    }

    public function content(): Content
    {
        return new Content(
            text: 'identity::mail.email-code',
            with: ['validMinutes' => EmailCode::VALID_MINUTES],
        );
    }
}
