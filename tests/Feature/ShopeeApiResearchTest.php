<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\ShopeeApiResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Middleware as HandleInertiaRequests;
use Tests\TestCase;

class ShopeeApiResearchTest extends TestCase
{
    use RefreshDatabase;

    private const STATUSES = ['available', 'partial', 'unavailable', 'unverified'];

    private function activeUser(): User
    {
        return User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
    }

    public function test_route_requires_authentication(): void
    {
        $this->get(route('integrations.shopee-api'))->assertRedirect('/login');
    }

    public function test_active_user_can_access_the_page(): void
    {
        $this->actingAs($this->activeUser())
            ->get(route('integrations.shopee-api'))
            ->assertOk();
    }

    public function test_page_is_accessible_for_multiple_users_and_tenants(): void
    {
        $first = $this->activeUser();
        $second = $this->activeUser();

        $this->actingAs($first)->get(route('integrations.shopee-api'))->assertOk();
        $this->actingAs($second)->get(route('integrations.shopee-api'))->assertOk();
    }

    public function test_page_exposes_research_metadata_only(): void
    {
        $user = $this->activeUser();
        $request = Request::create(route('integrations.shopee-api'));
        $version = app(HandleInertiaRequests::class)->version($request);

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('integrations.shopee-api'));

        $response->assertOk()->assertJsonStructure([
            'props' => [
                'scope' => ['title', 'generated_at', 'api_version', 'disclaimer'],
                'capabilities' => [['name', 'status', 'description', 'apis']],
                'endpoints' => [['endpoint', 'method', 'path', 'purpose']],
                'fieldMatrix' => [
                    'orders' => [['field', 'source', 'status', 'note']],
                    'income' => [['field', 'source', 'status', 'note']],
                ],
                'importRates' => [['report', 'rating', 'severity', 'summary', 'caveats']],
                'readiness' => [['item', 'status', 'note']],
                'architecture' => [],
            ],
        ]);
    }

    public function test_research_payload_contains_no_security_credentials(): void
    {
        $payload = app(ShopeeApiResearchService::class)->payload();
        $serialized = json_encode($payload);

        foreach (['partner_key', 'partner_id', 'access_token', 'refresh_token', 'api_key', 'password', 'client_secret'] as $credential) {
            $this->assertStringNotContainsString('"'.$credential.'":', $serialized);
        }

        $this->assertSame(0, preg_match('/"([a-f0-9]{32,}|eyJ[a-zA-Z0-9_~-]+\.[a-zA-Z0-9_~-]+\.[a-zA-Z0-9_~-]+)"/', $serialized));
    }

    public function test_all_capabilities_and_matrix_statuses_are_whitelisted(): void
    {
        $payload = app(ShopeeApiResearchService::class)->payload();

        foreach ($payload['capabilities'] as $capability) {
            $this->assertContains($capability['status'], self::STATUSES);
        }

        foreach (array_column($payload['fieldMatrix']['orders'], 'status') as $status) {
            $this->assertContains($status, self::STATUSES);
        }

        foreach (array_column($payload['fieldMatrix']['income'], 'status') as $status) {
            $this->assertContains($status, self::STATUSES);
        }
    }

    public function test_endpoints_expose_required_keys_and_faq_639_mapping(): void
    {
        $payload = app(ShopeeApiResearchService::class)->payload();

        foreach ($payload['endpoints'] as $endpoint) {
            foreach (['endpoint', 'method', 'path', 'purpose', 'pagination', 'keyParams'] as $key) {
                $this->assertArrayHasKey($key, $endpoint);
                $this->assertNotSame('', $endpoint[$key]);
            }
        }

        $sources = array_column($payload['fieldMatrix']['orders'], 'source');
        $this->assertNotEmpty(array_filter($sources, fn (string $source) => str_contains($source, 'get_escrow_detail')));
        $this->assertNotEmpty(array_filter($sources, fn (string $source) => str_contains($source, 'get_income_detail') || str_contains($source, 'get_return_list')));
    }

    public function test_income_field_matrix_marks_unmappable_fields(): void
    {
        $payload = app(ShopeeApiResearchService::class)->payload();
        $fields = collect($payload['fieldMatrix']['income'])->keyBy('field');

        $this->assertSame('unavailable', $fields['voucher_code']['status']);
        $this->assertSame('unavailable', $fields['product_id']['status']);
    }
}
