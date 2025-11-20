<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Mail\VerificationEmail;
use App\Mail\PasswordResetEmail;
use App\Services\EmailVerificationService;
use Illuminate\Support\Facades\Mail;

/**
 * Integration Test: Email Delivery Verification (T043)
 *
 * This test validates that emails are properly queued and contain correct content.
 * For actual SMTP delivery testing, see the manual testing guide below.
 */
class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Test verification email is queued on registration.
     *
     * @group integration
     * @group email
     */
    public function test_verification_email_is_queued_on_registration(): void
    {
        $email = 'emailtest@example.com';

        // Register user
        $this->postJson('/api/auth/register', ['email' => $email])
            ->assertStatus(201);

        // Assert verification email was queued
        Mail::assertQueued(VerificationEmail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });

        // Assert email was queued (not sent immediately)
        Mail::assertNotSent(VerificationEmail::class);
    }

    /**
     * Test verification email contains correct verification URL.
     *
     * @group integration
     * @group email
     */
    public function test_verification_email_contains_correct_url(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('urltest@example.com');

        $mailable = new VerificationEmail($token);

        // Verify URL is generated correctly
        $this->assertStringContainsString('urltest@example.com', $mailable->verificationUrl);
        $this->assertStringContainsString('verify-email', $mailable->verificationUrl);
        $this->assertStringContainsString('token=', $mailable->verificationUrl);
        $this->assertEquals('24', $mailable->expirationHours);
    }

    /**
     * Test verification email has correct subject and properties.
     *
     * @group integration
     * @group email
     */
    public function test_verification_email_has_correct_properties(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('properties@example.com');

        $mailable = new VerificationEmail($token);
        $envelope = $mailable->envelope();

        // Verify subject
        $this->assertEquals('電子郵件驗證 - DISINFO_SCANNER', $envelope->subject);

        // Verify content uses correct view
        $content = $mailable->content();
        $this->assertEquals('emails.verify-email', $content->view);

        // Verify data passed to view
        $viewData = $content->with;
        $this->assertArrayHasKey('verificationUrl', $viewData);
        $this->assertArrayHasKey('expirationHours', $viewData);
    }

    /**
     * Test password reset email is queued correctly.
     *
     * @group integration
     * @group email
     */
    public function test_password_reset_email_is_queued(): void
    {
        $email = 'resettest@example.com';
        $token = 'test-reset-token-123';

        $mailable = new PasswordResetEmail($email, $token);

        Mail::to($email)->queue($mailable);

        // Assert password reset email was queued
        Mail::assertQueued(PasswordResetEmail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    /**
     * Test password reset email contains correct reset URL.
     *
     * @group integration
     * @group email
     */
    public function test_password_reset_email_contains_correct_url(): void
    {
        $email = 'reseturl@example.com';
        $token = 'reset-token-456';

        $mailable = new PasswordResetEmail($email, $token);

        // Verify URL is generated correctly
        $this->assertStringContainsString($email, $mailable->resetUrl);
        $this->assertStringContainsString('password/reset', $mailable->resetUrl);
        $this->assertStringContainsString('token=', $mailable->resetUrl);
        $this->assertEquals('60', $mailable->expirationMinutes);
    }

    /**
     * Test password reset email has correct subject.
     *
     * @group integration
     * @group email
     */
    public function test_password_reset_email_has_correct_subject(): void
    {
        $mailable = new PasswordResetEmail('test@example.com', 'token123');
        $envelope = $mailable->envelope();

        $this->assertEquals('重設密碼 - DISINFO_SCANNER', $envelope->subject);
    }

    /**
     * Test resend verification email functionality.
     *
     * @group integration
     * @group email
     */
    public function test_resend_verification_email(): void
    {
        $email = 'resend@example.com';

        // Initial registration
        $this->postJson('/api/auth/register', ['email' => $email])
            ->assertStatus(201);

        Mail::assertQueued(VerificationEmail::class, 1);

        // Resend verification
        $this->postJson('/api/auth/verify-email/resend', ['email' => $email])
            ->assertStatus(200);

        // Should have 2 emails queued now
        Mail::assertQueued(VerificationEmail::class, 2);
    }

    /**
     * Test email queue configuration is correct.
     *
     * @group integration
     * @group email
     */
    public function test_email_queue_configuration(): void
    {
        $email = 'queuetest@example.com';

        $this->postJson('/api/auth/register', ['email' => $email]);

        Mail::assertQueued(VerificationEmail::class, function ($mail) {
            // Verify email is queued (using Queueable trait)
            return true;
        });
    }
}
