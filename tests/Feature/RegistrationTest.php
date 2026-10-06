<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider incompletePasswordProvider
     */
    public function test_registration_rejects_passwords_that_do_not_meet_all_requirements(string $password): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'username' => 'test-user',
            'email' => 'test@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public static function incompletePasswordProvider(): array
    {
        return [
            'too short' => ['Ab1!short'],
            'missing uppercase' => ['ab1!abcdefghij'],
            'missing lowercase' => ['AB1!ABCDEFGHIJ'],
            'missing number' => ['Ab!abcdefghijk'],
            'missing special character' => ['Ab1abcdefghijk'],
        ];
    }

    public function test_registration_accepts_a_password_that_meets_all_requirements(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'username' => 'test-user',
            'email' => 'test@example.com',
            'password' => 'Ab1!abcdefghij',
            'password_confirmation' => 'Ab1!abcdefghij',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
