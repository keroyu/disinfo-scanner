<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use App\Mail\AdminBulkEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BatchEmailService
{
    /**
     * Result object for batch email operations
     */
    public static function createResult(
        int $totalRecipients,
        int $sentCount,
        int $failedCount,
        array $failedEmails = []
    ): array {
        return [
            'total_recipients' => $totalRecipients,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'failed_emails' => $failedEmails,
        ];
    }

    /**
     * Send email to multiple users
     *
     * @param array $userIds Array of user IDs to send email to
     * @param string $subject Email subject
     * @param string $body Email body content
     * @param int $adminId The admin performing the action
     * @return array BatchResult with operation details
     */
    public function send(array $userIds, string $subject, string $body, int $adminId): array
    {
        $totalRecipients = count($userIds);
        $sentCount = 0;
        $failedCount = 0;
        $failedEmails = [];
        $sentEmails = [];

        // Fetch users
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            try {
                Mail::to($user->email)->send(new AdminBulkEmail($subject, $body));
                $sentCount++;
                $sentEmails[] = $user->email;
            } catch (\Exception $e) {
                $failedCount++;
                $failedEmails[] = [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ];
                Log::error('Batch email send failed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Log the batch operation
        if ($sentCount > 0 || $failedCount > 0) {
            $admin = User::find($adminId);
            AuditLog::log(
                actionType: 'batch_email_sent',
                description: sprintf(
                    'Admin %s (%s) 批次發送郵件給 %d 位用戶（成功 %d，失敗 %d）- 主旨: %s',
                    $admin->name,
                    $admin->email,
                    $totalRecipients,
                    $sentCount,
                    $failedCount,
                    mb_substr($subject, 0, 50) . (mb_strlen($subject) > 50 ? '...' : '')
                ),
                userId: null,
                adminId: $adminId,
                resourceType: 'batch',
                resourceId: null,
                changes: [
                    'subject' => $subject,
                    'recipient_count' => $totalRecipients,
                    'sent_count' => $sentCount,
                    'failed_count' => $failedCount,
                    'sent_emails' => $sentEmails,
                    'failed_emails' => array_map(fn($f) => $f['email'], $failedEmails),
                ]
            );
        }

        return self::createResult(
            $totalRecipients,
            $sentCount,
            $failedCount,
            $failedEmails
        );
    }
}
