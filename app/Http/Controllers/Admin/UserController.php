<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()->latest()->paginate(25)->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'status' => $user->account_status->value,
                'trial_ends_at' => $user->trial_ends_at?->toIso8601String(),
                'subscription_ends_at' => $user->subscription_ends_at?->toIso8601String(),
            ]),
            'trialDays' => config('subscriptions.trial_days'),
        ]);
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
