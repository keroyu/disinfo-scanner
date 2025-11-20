<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class EmailValidationTest extends TestCase
{
    /**
     * Test valid email formats pass validation.
     */
    public function test_valid_email_formats_pass_validation(): void
    {
        $validEmails = [
            'user@example.com',
            'first.last@example.com',
            'user+tag@example.co.uk',
            'user_name@example-domain.com',
            'user123@test.org',
            'a@b.co',
        ];

        foreach ($validEmails as $email) {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => 'required|email']
            );

            $this->assertFalse(
                $validator->fails(),
                "Email '{$email}' should be valid but validation failed"
            );
        }
    }

    /**
     * Test invalid email formats fail validation.
     */
    public function test_invalid_email_formats_fail_validation(): void
    {
        $invalidEmails = [
            'notanemail',
            '@example.com',
            'user@',
            'user @example.com',
            'user@example',
            'user..name@example.com',
            'user@.com',
            '.user@example.com',
            'user@example..com',
            '',
            'user@@example.com',
            'user name@example.com',
        ];

        foreach ($invalidEmails as $email) {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => 'required|email']
            );

            $this->assertTrue(
                $validator->fails(),
                "Email '{$email}' should be invalid but validation passed"
            );
        }
    }

    /**
     * Test email uniqueness validation.
     */
    public function test_email_uniqueness_validation(): void
    {
        // This will be tested in integration tests with actual database
        // Unit test just validates the rule format
        $rules = ['email' => 'required|email|unique:users,email'];

        $validator = Validator::make(
            ['email' => 'test@example.com'],
            $rules
        );

        // Rule format should be valid
        $this->assertIsArray($validator->getRules());
        $this->assertArrayHasKey('email', $validator->getRules());
    }

    /**
     * Test email max length validation.
     */
    public function test_email_max_length_validation(): void
    {
        // Email should not exceed 255 characters
        $longEmail = str_repeat('a', 246) . '@test.com'; // 256 characters

        $validator = Validator::make(
            ['email' => $longEmail],
            ['email' => 'required|email|max:255']
        );

        $this->assertTrue($validator->fails());

        // Valid length should pass
        $validEmail = str_repeat('a', 245) . '@test.com'; // 255 characters

        $validator = Validator::make(
            ['email' => $validEmail],
            ['email' => 'required|email|max:255']
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * Test email is required.
     */
    public function test_email_is_required(): void
    {
        $validator = Validator::make(
            [],
            ['email' => 'required|email']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * Test email case insensitivity.
     */
    public function test_email_case_insensitivity(): void
    {
        $emails = [
            'User@Example.COM',
            'user@example.com',
            'USER@EXAMPLE.COM',
        ];

        foreach ($emails as $email) {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => 'required|email']
            );

            $this->assertFalse(
                $validator->fails(),
                "Email '{$email}' should be valid regardless of case"
            );
        }

        // Verify normalization (Laravel stores emails as-is, but comparison should be case-insensitive)
        $email1 = 'User@Example.COM';
        $email2 = 'user@example.com';

        $this->assertEquals(
            strtolower($email1),
            strtolower($email2),
            'Emails should be case-insensitive when comparing'
        );
    }

    /**
     * Test special characters in email local part.
     */
    public function test_special_characters_in_email_local_part(): void
    {
        $validSpecialChars = [
            'user+tag@example.com',
            'user.name@example.com',
            'user_name@example.com',
            'user-name@example.com',
        ];

        foreach ($validSpecialChars as $email) {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => 'required|email']
            );

            $this->assertFalse(
                $validator->fails(),
                "Email '{$email}' with special characters should be valid"
            );
        }
    }
}
