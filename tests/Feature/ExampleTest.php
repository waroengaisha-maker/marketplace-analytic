<?php

namespace Tests\Feature;

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
        $user = User::factory()->create();

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
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }
}