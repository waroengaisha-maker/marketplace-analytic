<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_dashboard(): void
    {
        $this->get('/')
            ->assertRedirect('/login');
    }

    public function test_authenticated_users_can_open_the_dashboard_with_shared_user_props(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Active]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.email', $user->email)
            );
    }

    public function test_users_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'username' => 'test-user',
            'phone' => '081234567890',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'login' => 'user@example.com',
            'password' => 'password',
        ])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_log_in_with_a_username(): void
    {
        $user = User::factory()->create([
            'username' => 'test-user',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'login' => 'test-user',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_log_in_with_a_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => '081234567890',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'login' => '081234567890',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_guests_can_register_with_login_identifiers(): void
    {
        $this->post('/register', [
            'name' => 'Registered User',
            'username' => 'registered-user',
            'email' => 'registered@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'username' => 'registered-user',
            'email' => 'registered@example.com',
            'phone' => '081234567890',
        ]);
    }
}
