<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * T059: Role Change Notification Email
 *
 * Sent to users when their role is changed by an administrator.
 * Includes role name, premium expiry (if applicable), and links to Terms/Points Guide.
 */
class RoleChangeNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The user receiving the notification.
     */
    public User $user;

    /**
     * The new role assigned to the user.
     */
    public Role $newRole;

    /**
     * Premium membership expiration date (nullable).
     */
    public ?Carbon $premiumExpiresAt;

    /**
     * Whether the user was previously suspended and is now unsuspended.
     */
    public bool $wasUnsuspended;

    /**
     * Whether the user is being suspended.
     */
    public bool $isSuspended;

    /**
     * Create a new message instance.
     *
     * @param User $user The user whose role was changed
     * @param Role $newRole The new role assigned
     * @param Carbon|null $premiumExpiresAt Premium expiry date (only for Premium Members)
     * @param bool $wasUnsuspended True if user was previously suspended
     */
    public function __construct(
        User $user,
        Role $newRole,
        ?Carbon $premiumExpiresAt = null,
        bool $wasUnsuspended = false
    ) {
        $this->user = $user;
        $this->newRole = $newRole;
        $this->premiumExpiresAt = $premiumExpiresAt;
        $this->wasUnsuspended = $wasUnsuspended;
        $this->isSuspended = $newRole->name === 'suspended';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '帳號權限變更通知',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.role-change-notification',
            with: [
                'userName' => $this->user->name,
                'newRoleName' => $this->newRole->display_name,
                'premiumExpiresAt' => $this->premiumExpiresAt
                    ? $this->premiumExpiresAt->timezone('Asia/Taipei')->format('Y-m-d H:i') . ' (GMT+8)'
                    : null,
                'isPremiumMember' => $this->newRole->name === 'premium_member',
                'isSuspended' => $this->isSuspended,
                'wasUnsuspended' => $this->wasUnsuspended,
                'termsUrl' => 'https://ds.yueyuknows.com/terms',
                'pointsGuideUrl' => 'https://ds.yueyuknows.com/points-guide',
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
