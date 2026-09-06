<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_users_are_redirected_to_account_status(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);

        $this->actingAs($user)->get('/')->assertRedirect('/account/status');
    }

    public function test_active_users_can_access_the_dashboard(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->get('/imports/upload')->assertOk();
    }

    public function test_expired_trials_are_blocked_and_admins_bypass_account_gate(): void
    {
        $expired = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->subDay(),
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($expired)->get('/')->assertRedirect('/account/status');
        $this->actingAs($admin)->get('/imports/upload')->assertOk();
    }

    public function test_admin_can_activate_a_user_and_an_audit_log_is_created(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);

        $this->actingAs($admin)
            ->post(route('admin.users.activate', $user), ['trial_days' => 30])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(AccountStatus::Active, $user->account_status);
        $this->assertTrue($user->trial_ends_at->isFuture());
        $this->assertDatabaseHas('account_audit_logs', [
            'user_id' => $user->id,
            'actor_id' => $admin->id,
            'action' => 'activated',
        ]);
    }

    public function test_regular_users_cannot_open_admin_routes(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Active]);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }
}
