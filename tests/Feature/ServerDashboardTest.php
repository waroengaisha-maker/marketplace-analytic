<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ServerDashboardTest extends TestCase
{
    private string $snapshotPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotPath = storage_path('app/server-status-test.json');
        config(['server-status.snapshot_path' => $this->snapshotPath]);
    }

    protected function tearDown(): void
    {
        File::delete($this->snapshotPath);

        parent::tearDown();
    }

    public function test_super_admin_can_view_server_dashboard(): void
    {
        File::put($this->snapshotPath, json_encode([
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'host' => 'fedora',
            'overall' => 'ok',
            'system' => [
                'uptime' => 'up 2 hours',
                'load_1m' => 0.14,
                'memory_used' => '1.9Gi',
                'memory_total' => '6.7Gi',
            ],
            'disk' => ['percent' => 50],
            'docker' => [
                'caddy' => ['status' => 'running', 'health' => 'no-healthcheck'],
            ],
            'application' => ['status' => 'ok', 'url' => 'http://127.0.0.1:8080'],
            'backup' => [
                'timer_enabled' => true,
                'latest' => 'mysql-test.sql.gz',
                'size' => '8.0K',
                'integrity' => 'ok',
            ],
            'storage' => ['smart' => 'PASSED'],
        ], JSON_THROW_ON_ERROR));

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        config([
            'adminer.local_url' => 'http://localhost:8081',
            'adminer.server_url' => 'https://adminer.marketplace-analytics.my.id',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.server.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Server/Index')
                ->where('status.available', true)
                ->where('status.overall', 'ok')
                ->where('status.host', 'fedora')
                ->where('status.disk.percent', 50)
                ->where('adminer.local_url', 'http://localhost:8081')
                ->where('adminer.server_url', 'https://adminer.marketplace-analytics.my.id')
            );
    }

    public function test_regular_admin_cannot_view_server_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.server.index'))
            ->assertForbidden();
    }

    public function test_missing_snapshot_is_reported_as_unavailable(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->get(route('admin.server.index'))
            ->assertInertia(fn ($page) => $page
                ->where('status.available', false)
            );
    }
    public function test_super_admin_can_view_services_hub(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Services/Index')
                ->has('services', 4)
                ->where('services.0.key', 'netdata')
                ->where('services.1.key', 'portainer')
                ->where('services.2.key', 'adminer')
                ->where('services.3.key', 'redisinsight')
            );
    }

    public function test_super_admin_is_authorized_for_service_auth(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->get(route('internal.service-auth'))
            ->assertNoContent()
            ->assertHeader('Cache-Control', 'no-store');
    }

    public function test_regular_admin_is_denied_service_auth(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('internal.service-auth'))
            ->assertForbidden();
    }

    public function test_guest_is_denied_service_auth_without_redirect(): void
    {
        $this->get(route('internal.service-auth'))
            ->assertForbidden()
            ->assertHeaderMissing('Location');
    }

    public function test_regular_admin_cannot_view_services_hub(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.services.index'))
            ->assertForbidden();
    }

}
