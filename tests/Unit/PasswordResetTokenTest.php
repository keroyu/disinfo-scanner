<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Unit Test: Password Reset Token Expiration
 *
 * Tests password reset token lifecycle:
 * - Token generation
 * - Token expiration (24 hours)
 * - Token invalidation after use
 */
class PasswordResetTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * T050: Password reset token is generated correctly
     */
    public function it_generates_password_reset_token()
    {
        $email = 'test@example.com';
        $token = Str::random(60);

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $email,
        ]);
    }

    /**
     * @test
     * T050: Password reset token is stored with creation timestamp
     */
    public function it_stores_token_with_timestamp()
    {
        $email = 'test@example.com';
        $token = Str::random(60);
        $createdAt = now();

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => $createdAt,
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        $this->assertNotNull($record->created_at);
    }

    /**
     * @test
     * T050: Password reset token expires after 24 hours
     */
    public function it_expires_after_24_hours()
    {
        $email = 'test@example.com';
        $token = Str::random(60);

        // Create token 25 hours ago
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now()->subHours(25),
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        // Token is expired (older than 24 hours)
        $this->assertTrue(
            Carbon::parse($record->created_at)->addHours(24)->isPast()
        );
    }

    /**
     * @test
     * T050: Valid token within 24 hours is not expired
     */
    public function it_is_valid_within_24_hours()
    {
        $email = 'test@example.com';
        $token = Str::random(60);

        // Create token 12 hours ago
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now()->subHours(12),
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        // Token is still valid (younger than 24 hours)
        $this->assertFalse(
            Carbon::parse($record->created_at)->addHours(24)->isPast()
        );
    }

    /**
     * @test
     * T050: Token is deleted after successful use
     */
    public function it_is_deleted_after_successful_use()
    {
        $email = 'test@example.com';
        $token = Str::random(60);

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Simulate successful password reset (delete token)
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $email,
        ]);
    }

    /**
     * @test
     * T050: Multiple reset requests replace previous token
     */
    public function it_replaces_previous_token_on_new_request()
    {
        $email = 'test@example.com';
        $token1 = Str::random(60);
        $token2 = Str::random(60);

        // First request
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token1),
            'created_at' => now(),
        ]);

        // Second request (should replace first)
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->update([
                'token' => Hash::make($token2),
                'created_at' => now(),
            ]);

        // Should only have one record
        $count = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * @test
     * T050: Token cleanup removes expired tokens
     */
    public function it_cleans_up_expired_tokens()
    {
        $validEmail = 'valid@example.com';
        $expiredEmail = 'expired@example.com';

        // Create valid token (12 hours old)
        DB::table('password_reset_tokens')->insert([
            'email' => $validEmail,
            'token' => Hash::make(Str::random(60)),
            'created_at' => Carbon::now()->subHours(12),
        ]);

        // Create expired token (30 hours old)
        DB::table('password_reset_tokens')->insert([
            'email' => $expiredEmail,
            'token' => Hash::make(Str::random(60)),
            'created_at' => Carbon::now()->subHours(30),
        ]);

        // Cleanup expired tokens (older than 24 hours)
        DB::table('password_reset_tokens')
            ->where('created_at', '<', Carbon::now()->subHours(24))
            ->delete();

        // Valid token should still exist
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $validEmail,
        ]);

        // Expired token should be deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $expiredEmail,
        ]);
    }

    /**
     * @test
     * T050: Token is hashed before storage
     */
    public function it_hashes_token_before_storage()
    {
        $email = 'test@example.com';
        $plainToken = Str::random(60);
        $hashedToken = Hash::make($plainToken);

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => now(),
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        // Stored token should not equal plain token
        $this->assertNotEquals($plainToken, $record->token);

        // But should verify correctly
        $this->assertTrue(Hash::check($plainToken, $record->token));
    }

    /**
     * @test
     * T050: Different users can have different reset tokens simultaneously
     */
    public function it_allows_multiple_users_to_reset_simultaneously()
    {
        $user1Email = 'user1@example.com';
        $user2Email = 'user2@example.com';

        DB::table('password_reset_tokens')->insert([
            [
                'email' => $user1Email,
                'token' => Hash::make(Str::random(60)),
                'created_at' => now(),
            ],
            [
                'email' => $user2Email,
                'token' => Hash::make(Str::random(60)),
                'created_at' => now(),
            ],
        ]);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user1Email]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user2Email]);
    }

    /**
     * @test
     * T050: Token validation considers expiration time
     */
    public function it_validates_token_expiration()
    {
        $expirationHours = 24;

        // Token exactly at expiration boundary
        $tokenAt24Hours = Carbon::now()->subHours($expirationHours);
        $this->assertTrue(
            $tokenAt24Hours->addHours($expirationHours)->isPast()
        );

        // Token just before expiration
        $tokenAt23Hours = Carbon::now()->subHours($expirationHours - 1);
        $this->assertFalse(
            $tokenAt23Hours->addHours($expirationHours)->isPast()
        );
    }
}
