<?php

namespace App\Mail;

use App\Models\PaymentProduct;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public PaymentProduct $product;
    public string $productName;
    public string $expiryDate;
    public string $siteUrl;
    public string $pointSystemUrl;
    public string $supportEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, PaymentProduct $product)
    {
        $this->user = $user;
        $this->product = $product;
        $this->productName = $product->name;
        $this->expiryDate = $user->premium_expires_at
            ? $user->premium_expires_at->format('Y-m-d')
            : now()->addDays($product->duration_days)->format('Y-m-d');
        $this->siteUrl = config('app.url');
        $this->pointSystemUrl = config('app.url') . '/point-system';
        $this->supportEmail = 'themustbig+ds@gmail.com';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "付款成功 - {$this->productName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-confirmation',
            with: [
                'user' => $this->user,
                'productName' => $this->productName,
                'expiryDate' => $this->expiryDate,
                'siteUrl' => $this->siteUrl,
                'pointSystemUrl' => $this->pointSystemUrl,
                'supportEmail' => $this->supportEmail,
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
