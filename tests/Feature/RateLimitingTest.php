<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_shopee_sync_limit_is_scoped_to_authenticated_user(): void
    {
        $userA = $this->activeUser();
        $userB = $this->activeUser();

        RateLimiter::clear('user:'.$userA->id);
        RateLimiter::clear('user:'.$userB->id);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($userA)
                ->postJson(route('integrations.shopee-api.sync-orders'))
                ->assertStatus(422);
        }

        $this->actingAs($userA)
            ->postJson(route('integrations.shopee-api.sync-orders'))
            ->assertStatus(429);

        $this->actingAs($userB)
            ->postJson(route('integrations.shopee-api.sync-orders'))
            ->assertStatus(422);
    }

    public function test_report_upload_limit_is_scoped_to_authenticated_user(): void
    {
        $userA = $this->activeUser();
        $userB = $this->activeUser();

        RateLimiter::clear('user:'.$userA->id);
        RateLimiter::clear('user:'.$userB->id);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($userA)
                ->postJson(route('imports.upload.store'))
                ->assertStatus(422);
        }

        $this->actingAs($userA)
            ->postJson(route('imports.upload.store'))
            ->assertStatus(429);

        $this->actingAs($userB)
            ->postJson(route('imports.upload.store'))
            ->assertStatus(422);
    }

    private function activeUser(): User
    {
        return User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
    }
}
