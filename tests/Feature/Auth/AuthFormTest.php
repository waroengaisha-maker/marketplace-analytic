<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rejects_invalid_credentials_without_revealing_account_state(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'CorrectPassword1!',
            'account_status' => AccountStatus::Active,
        ]);

        $this->from('/login')
            ->post('/login', [
                'login' => 'user@example.com',
                'password' => 'WrongPassword1!',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['login']);

        $this->assertGuest();
    }

    public function test_registration_rejects_password_that_does_not_meet_the_shared_policy(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Registered User',
                'username' => 'registered-user',
                'email' => 'registered@example.com',
                'phone' => null,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'email' => 'registered@example.com',
        ]);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->from('/forgot-password')
            ->post('/forgot-password', [
                'email' => 'invalid-email',
            ])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email']);
    }

    public function test_successful_password_reset_returns_to_reset_page_with_success_feedback(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'OldPassword1!',
            'account_status' => AccountStatus::Active,
        ]);

        $token = Password::broker()->createToken($user);

        $this->from('/reset-password?token='.$token.'&email='.urlencode($user->email))
            ->post('/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertRedirect('/reset-password?token='.$token.'&email='.urlencode($user->email))
            ->assertSessionHas('success', 'Password berhasil diubah. Silakan login menggunakan password baru Anda.');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
    }

    public function test_password_reset_rejects_password_that_does_not_meet_the_shared_policy(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'OldPassword1!',
        ]);

        $token = Password::broker()->createToken($user);

        $this->from('/reset-password?token='.$token.'&email='.urlencode($user->email))
            ->post('/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect('/reset-password?token='.$token.'&email='.urlencode($user->email))
            ->assertSessionHasErrors(['password']);
    }
}
