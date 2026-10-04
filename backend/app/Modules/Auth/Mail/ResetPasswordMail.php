<?php

namespace App\Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $resetUrl) {}

    public function build(): self
    {
        return $this->subject('Đặt lại mật khẩu H-Smart')->text('mail.reset-password');
    }
}
