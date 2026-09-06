<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AccountAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        return Inertia::render('Admin/Users/Index', [
            ...$this->userProps(UserRole::Admin),
            'canManageRoles' => true,
        ]);
    }

    public function access(): Response
    {
        return Inertia::render('Admin/Users/Access', $this->userProps(UserRole::User));
    }

    /**
     * @return array<string, mixed>
     */
    private function userProps(UserRole $role): array
    {
        return [
            'users' => User::query()
                ->where('role', $role->value)
                ->latest()
                ->paginate(25)
                ->through(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'role' => $user->role->value,
                    'status' => $user->account_status->value,
                    'trial_ends_at' => $user->trial_ends_at?->toIso8601String(),
                    'subscription_ends_at' => $user->subscription_ends_at?->toIso8601String(),
                    'is_admin' => $user->isAdmin(),
                ]),
            'trialDays' => config('subscriptions.trial_days'),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'max:50', Rule::unique(User::class)],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);
        $role = UserRole::from($data['role']);
        abort_unless(
            request()->user()?->isSuperAdmin() || (request()->user()?->isAdmin() && $role === UserRole::User),
            403,
        );

        $user = User::create([
            ...$data,
            'role' => $role,
            'account_status' => AccountStatus::Pending,
        ]);
        $this->audit($request, $user, 'created', ['role' => $role->value]);

        return back()->with('success', 'Account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageAccount', $user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'max:50', Rule::unique(User::class)->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique(User::class)->ignore($user)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
        $attributes = collect($data)->except(['password', 'password_confirmation'])->all();
        $user->fill($attributes);
        if ($data['password'] !== null) {
            $user->password = $data['password'];
        }
        $user->save();
        $this->audit($request, $user, 'updated');

        return back()->with('success', 'Account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageAccount', $user);
        $user->delete();

        return back()->with('success', 'Account deleted.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageAccount', $user);
        $data = $request->validate([
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);
        $days = $data['trial_days'] ?? config('subscriptions.trial_days');
        $now = now();

        $user->forceFill([
            'account_status' => AccountStatus::Active,
            'trial_started_at' => $now,
            'trial_ends_at' => $days > 0 ? $now->copy()->addDays($days) : null,
            'activated_at' => $now,
            'suspended_at' => null,
        ])->save();
        $this->audit($request, $user, 'activated', ['trial_days' => $days]);

        return back()->with('success', 'User activated.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageAccount', $user);
        $user->forceFill(['account_status' => AccountStatus::Suspended, 'suspended_at' => now()])->save();
        $this->audit($request, $user, 'suspended');

        return back()->with('success', 'User suspended.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageRole', $user);
        $data = $request->validate([
            'role' => ['required', 'string', 'in:admin,user'],
        ]);
        $user->forceFill(['role' => UserRole::from($data['role'])])->save();
        $this->audit($request, $user, 'role_updated', ['role' => $data['role']]);

        return back()->with('success', 'User role updated.');
    }

    public function trial(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageAccount', $user);
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);
        $now = now();
        $user->forceFill([
            'account_status' => AccountStatus::Active,
            'trial_started_at' => $now,
            'trial_ends_at' => $data['trial_days'] > 0 ? $now->copy()->addDays($data['trial_days']) : null,
            'activated_at' => $user->activated_at ?? $now,
            'suspended_at' => null,
        ])->save();
        $this->audit($request, $user, 'trial_set', $data);

        return back()->with('success', 'Trial updated.');
    }

    private function audit(Request $request, User $user, string $action, array $metadata = []): void
    {
        AccountAuditLog::create([
            'user_id' => $user->id,
            'actor_id' => $request->user()->id,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }
}
