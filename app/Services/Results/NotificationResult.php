<?php

namespace App\Services\Results;

/**
 * T064: Value object for notification operation results
 *
 * Tracks the outcome of role change notification emails sent to users.
 */
class NotificationResult
{
    /**
     * Number of notifications successfully sent.
     */
    public int $sentCount;

    /**
     * Number of notifications that failed to send.
     */
    public int $failedCount;

    /**
     * IDs of users whose notifications failed.
     */
    public array $failedUserIds;

    /**
     * Email addresses of users whose notifications failed.
     */
    public array $failedEmails;

    /**
     * Create a new NotificationResult instance.
     *
     * @param int $sentCount Number of successful notifications
     * @param int $failedCount Number of failed notifications
     * @param array $failedUserIds IDs of users whose notifications failed
     * @param array $failedEmails Emails of users whose notifications failed
     */
    public function __construct(
        int $sentCount = 0,
        int $failedCount = 0,
        array $failedUserIds = [],
        array $failedEmails = []
    ) {
        $this->sentCount = $sentCount;
        $this->failedCount = $failedCount;
        $this->failedUserIds = $failedUserIds;
        $this->failedEmails = $failedEmails;
    }

    /**
     * Check if all notifications were sent successfully.
     */
    public function allSuccessful(): bool
    {
        return $this->failedCount === 0;
    }

    /**
     * Check if any notifications were sent.
     */
    public function anySuccessful(): bool
    {
        return $this->sentCount > 0;
    }

    /**
     * Get total attempted notifications.
     */
    public function totalAttempted(): int
    {
        return $this->sentCount + $this->failedCount;
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'notifications_sent' => $this->sentCount,
            'notifications_failed' => $this->failedCount,
            'failed_user_ids' => $this->failedUserIds,
            'failed_emails' => $this->failedEmails,
        ];
    }

    /**
     * Create a result indicating no notifications were needed.
     */
    public static function none(): self
    {
        return new self(0, 0, [], []);
    }

    /**
     * Create a successful result.
     */
    public static function success(int $count): self
    {
        return new self($count, 0, [], []);
    }

    /**
     * Add a failed notification.
     */
    public function addFailure(int $userId, string $email): void
    {
        $this->failedCount++;
        $this->failedUserIds[] = $userId;
        $this->failedEmails[] = $email;
    }

    /**
     * Increment success count.
     */
    public function addSuccess(): void
    {
        $this->sentCount++;
    }
}
