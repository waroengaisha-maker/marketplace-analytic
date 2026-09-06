<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreatesNewUsers::class, CreateNewUser::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $login = (string) $request->input('login');

            return Limit::perMinute(5)->by(strtolower($login).'|'.$request->ip());
        });

        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = trim((string) $request->input('login'));
            $user = User::query()
                ->where('email', $login)
                ->orWhere('username', $login)
                ->orWhere('phone', $login)
                ->first();

            return $user !== null && Hash::check((string) $request->input('password'), $user->password)
                ? $user
                : null;
        });

        Fortify::loginView(function () {
            return Inertia::render('Auth/Login');
        });

        Fortify::registerView(function () {
            return Inertia::render('Auth/Register');
        });

        Fortify::requestPasswordResetLinkView(function () {
            return Inertia::render('Auth/ForgotPassword');
        });

        Fortify::resetPasswordView(function (Request $request) {
            return Inertia::render('Auth/ResetPassword', [
                'email' => $request->email,
                'token' => $request->route('token'),
            ]);
        });
    }
}
