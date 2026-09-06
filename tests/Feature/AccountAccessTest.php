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

    public function test_active_admin_can_access_the_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'account_status' => AccountStatus::Active,
        ]);

        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.users.index'));
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
        $this->assertSame('trialing', $user->subscription_status->value);
        $this->assertSame('not_required', $user->payment_status->value);
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

    public function test_only_super_admins_can_manage_admin_roles(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $target = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.users.role', $target), ['role' => 'user'])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->post(route('admin.users.role', $target), ['role' => 'user'])
            ->assertRedirect();

        $this->assertSame(UserRole::User, $target->refresh()->role);
    }

    public function test_super_admin_has_separate_admin_and_application_user_interfaces(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($superAdmin)
            ->get(route('admin.admins.index'))
            ->assertInertia(fn ($page) => $page
                ->where('users.data.0.id', $admin->id)
            );

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn ($page) => $page
                ->where('users.data.0.id', $user->id)
            );
    }

    public function test_admin_only_has_application_user_interface(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.admins.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_crud_admin_and_application_user_accounts(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name' => 'New Admin',
            'username' => 'new-admin',
            'email' => 'new-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertRedirect();

        $admin = User::where('email', 'new-admin@example.com')->firstOrFail();
        $this->assertSame(UserRole::Admin, $admin->role);

        $this->actingAs($superAdmin)->put(route('admin.users.update', $admin), [
            'name' => 'Updated Admin',
            'username' => 'updated-admin',
            'email' => 'updated-admin@example.com',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect();

        $this->assertSame('Updated Admin', $admin->refresh()->name);

        $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $admin))->assertRedirect();
        $this->assertModelMissing($admin);
    }

    public function test_admin_can_crud_application_users_but_not_admins(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $targetAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'username' => 'new-user',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
        ])->assertRedirect();

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertSame(UserRole::User, $user->role);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $targetAdmin))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect();
        $this->assertModelMissing($user);
    }
}
