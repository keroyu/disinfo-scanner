<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $resetUrl;
    public string $expirationMinutes;

    /**
     * Create a new message instance.
     */
    public function __construct(string $email, string $token)
    {
        $this->resetUrl = route('password.reset', [
            'email' => $email,
            'token' => $token,
        ]);
        $this->expirationMinutes = '60';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '重設密碼 - DISINFO_SCANNER',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password',
            with: [
                'resetUrl' => $this->resetUrl,
                'expirationMinutes' => $this->expirationMinutes,
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
