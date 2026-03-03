<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otpCode,
        public readonly string $purpose, // 'register' or 'login'
        public readonly int $expirationMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->purpose === 'register'
            ? '帳號註冊驗證碼 - DISINFO_SCANNER'
            : '登入驗證碼 - DISINFO_SCANNER';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp-code',
            with: [
                'otpCode' => $this->otpCode,
                'purpose' => $this->purpose,
                'expirationMinutes' => $this->expirationMinutes,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
