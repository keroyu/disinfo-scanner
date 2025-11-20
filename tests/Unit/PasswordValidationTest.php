<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PasswordService;

/**
 * Unit Test: Password Strength Validation
 *
 * Tests password strength requirements:
 * - Minimum 8 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 * - At least one special character
 */
class PasswordValidationTest extends TestCase
{
    protected PasswordService $passwordService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordService = app(PasswordService::class);
    }

    /**
     * @test
     * T049: Password validation accepts strong passwords
     */
    public function it_accepts_strong_passwords()
    {
        $strongPasswords = [
            'SecurePass@123',
            'MyP@ssw0rd',
            'Test!ng123',
            'Abcd@1234',
            'P@ssword123',
            'Str0ng!Pass',
        ];

        foreach ($strongPasswords as $password) {
            $this->assertTrue(
                $this->passwordService->isPasswordStrong($password),
                "Password '{$password}' should be considered strong"
            );
        }
    }

    /**
     * @test
     * T049: Password validation rejects passwords without uppercase
     */
    public function it_rejects_passwords_without_uppercase()
    {
        $passwords = [
            'alllowercase',
            'lowercase123',
            'lowercase@123',
            'test!ng123',
        ];

        foreach ($passwords as $password) {
            $this->assertFalse(
                $this->passwordService->isPasswordStrong($password),
                "Password '{$password}' should be rejected (no uppercase)"
            );
        }
    }

    /**
     * @test
     * T049: Password validation rejects passwords without lowercase
     */
    public function it_rejects_passwords_without_lowercase()
    {
        $passwords = [
            'ALLUPPERCASE',
            'UPPERCASE123',
            'UPPERCASE@123',
            'TEST!NG123',
        ];

        foreach ($passwords as $password) {
            $this->assertFalse(
                $this->passwordService->isPasswordStrong($password),
                "Password '{$password}' should be rejected (no lowercase)"
            );
        }
    }

    /**
     * @test
     * T049: Password validation rejects passwords without numbers
     */
    public function it_rejects_passwords_without_numbers()
    {
        $passwords = [
            'NoNumbers!',
            'Test!ng',
            'Password@',
            'Abcd@efgh',
        ];

        foreach ($passwords as $password) {
            $this->assertFalse(
                $this->passwordService->isPasswordStrong($password),
                "Password '{$password}' should be rejected (no numbers)"
            );
        }
    }

    /**
     * @test
     * T049: Password validation rejects passwords without special characters
     */
    public function it_rejects_passwords_without_special_characters()
    {
        $passwords = [
            'NoSpecial123',
            'Testing123',
            'Password123',
            'Abcd1234',
        ];

        foreach ($passwords as $password) {
            $this->assertFalse(
                $this->passwordService->isPasswordStrong($password),
                "Password '{$password}' should be rejected (no special characters)"
            );
        }
    }

    /**
     * @test
     * T049: Password validation rejects passwords too short
     */
    public function it_rejects_passwords_too_short()
    {
        $passwords = [
            'Abc@1',      // 5 characters
            'Test@1',     // 6 characters
            'Pass@12',    // 7 characters
        ];

        foreach ($passwords as $password) {
            $this->assertFalse(
                $this->passwordService->isPasswordStrong($password),
                "Password '{$password}' should be rejected (too short)"
            );
        }
    }

    /**
     * @test
     * T049: Password validation accepts exactly 8 characters if strong
     */
    public function it_accepts_minimum_length_if_strong()
    {
        $password = 'Pass@123'; // Exactly 8 characters with all requirements

        $this->assertTrue(
            $this->passwordService->isPasswordStrong($password),
            "Password '{$password}' should be accepted (meets minimum length and all requirements)"
        );
    }

    /**
     * @test
     * T049: Password validation accepts long passwords
     */
    public function it_accepts_long_strong_passwords()
    {
        $passwords = [
            'VeryLongSecurePassword@123456789',
            'ThisIsAVerySecureP@ssw0rd',
            'ComplexPassword!WithNumbers123AndSymbols',
        ];

        foreach ($passwords as $password) {
            $this->assertTrue(
                $this->passwordService->isPasswordStrong($password),
                "Password '{$password}' should be accepted (long and strong)"
            );
        }
    }

    /**
     * @test
     * T049: Password validation handles edge cases
     */
    public function it_handles_edge_cases()
    {
        // Empty password
        $this->assertFalse($this->passwordService->isPasswordStrong(''));

        // Only spaces
        $this->assertFalse($this->passwordService->isPasswordStrong('        '));

        // Special characters count toward requirements
        $this->assertTrue($this->passwordService->isPasswordStrong('P@ssw0rd'));

        // Multiple special characters
        $this->assertTrue($this->passwordService->isPasswordStrong('P@$$w0rd!'));
    }

    /**
     * @test
     * T049: Password hashing produces different hashes for same password
     */
    public function it_produces_different_hashes_for_same_password()
    {
        $password = 'SecurePass@123';

        $hash1 = $this->passwordService->hashPassword($password);
        $hash2 = $this->passwordService->hashPassword($password);

        // Hashes should be different (due to salt)
        $this->assertNotEquals($hash1, $hash2);

        // But both should verify correctly
        $this->assertTrue($this->passwordService->verifyPassword($password, $hash1));
        $this->assertTrue($this->passwordService->verifyPassword($password, $hash2));
    }

    /**
     * @test
     * T049: Password verification works correctly
     */
    public function it_verifies_passwords_correctly()
    {
        $password = 'SecurePass@123';
        $hash = $this->passwordService->hashPassword($password);

        // Correct password
        $this->assertTrue($this->passwordService->verifyPassword($password, $hash));

        // Wrong password
        $this->assertFalse($this->passwordService->verifyPassword('WrongPassword', $hash));
    }

    /**
     * @test
     * T049: Default password is correctly identified
     */
    public function it_identifies_default_password()
    {
        $this->assertTrue($this->passwordService->isDefaultPassword('123456'));
        $this->assertFalse($this->passwordService->isDefaultPassword('SecurePass@123'));
    }

    /**
     * @test
     * T049: Password strength validation provides detailed feedback
     */
    public function it_provides_detailed_validation_feedback()
    {
        $weakPassword = 'weak';
        $errors = $this->passwordService->getPasswordStrengthErrors($weakPassword);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);

        // Should contain errors for all requirements
        $this->assertContains('minimum_length', $errors);
        $this->assertContains('uppercase', $errors);
        $this->assertContains('number', $errors);
        $this->assertContains('special_character', $errors);
    }

    /**
     * @test
     * T049: Strong password has no validation errors
     */
    public function strong_password_has_no_errors()
    {
        $strongPassword = 'SecurePass@123';
        $errors = $this->passwordService->getPasswordStrengthErrors($strongPassword);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }
}
