<?php

namespace App\Mail;

use App\Models\EmailVerificationToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;
    public string $expirationHours;

    /**
     * Create a new message instance.
     */
    public function __construct(EmailVerificationToken $token)
    {
        $this->verificationUrl = route('verification.verify', [
            'email' => $token->email,
            'token' => $token->raw_token,
        ]);
        $this->expirationHours = '24';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '電子郵件驗證 - DISINFO_SCANNER',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
            with: [
                'verificationUrl' => $this->verificationUrl,
                'expirationHours' => $this->expirationHours,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
