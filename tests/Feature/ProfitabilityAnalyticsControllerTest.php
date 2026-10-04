<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitabilityAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_profitability_analytics_page_requires_authentication(): void
    {
        $this->get('/analytics/profitability')->assertRedirect('/login');
    }

    public function test_profitability_analytics_page_renders_without_querying_until_date_filter_is_applied(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get('/analytics/profitability')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Analytics/Profitability', false)
                ->where('rows', [])
                ->where('dimension', 'product')
                ->where('hasAppliedFilter', false)
            );
    }
}
