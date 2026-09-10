<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Http\Controllers\UploadReportsController;
use App\Http\Requests\UploadReportsRequest;
use App\Models\User;
use App\Services\IncomeReconciliationService;
use App\Services\IncomeReportImporter;
use App\Services\MarketplaceReconciliationService;
use App\Services\OrderReportImporter;
use App\Services\UploadReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Mockery;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_and_account_payloads_exclude_sensitive_user_attributes(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'two_factor_secret' => 'sentinel-secret',
            'two_factor_recovery_codes' => 'sentinel-recovery-codes',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', $user->name)
                ->where('auth.user.email', $user->email)
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->missing('auth.user.two_factor_secret')
                ->missing('auth.user.two_factor_recovery_codes')
            );

        foreach (['/account/status', '/account/subscription'] as $uri) {
            $this->actingAs($user)
                ->get($uri)
                ->assertInertia(fn ($page) => $page
                    ->where('user.id', $user->id)
                    ->where('user.email', $user->email)
                    ->missing('user.password')
                    ->missing('user.remember_token')
                    ->missing('user.two_factor_secret')
                    ->missing('user.two_factor_recovery_codes')
                );
        }
    }

    public function test_user_remains_compatible_with_fortify_two_factor_encryption(): void
    {
        $user = User::factory()->create();
        $secret = 'TEST-SECRET';
        $recoveryCodes = ['TEST-CODE-1', 'TEST-CODE-2'];

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $user->refresh();

        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $this->assertSame($recoveryCodes, $user->recoveryCodes());
        $this->assertNotSame($secret, $user->getRawOriginal('two_factor_secret'));
        $this->assertNotSame(json_encode($recoveryCodes), $user->getRawOriginal('two_factor_recovery_codes'));
    }

    public function test_fortify_two_factor_actions_preserve_encrypted_storage_and_confirmation(): void
    {
        $user = User::factory()->create();
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('generateSecretKey')->once()->with(16)->andReturn('TEST-SECRET');
        $provider->shouldReceive('verify')->once()->with('TEST-SECRET', 'TEST-CODE')->andReturnTrue();

        (new EnableTwoFactorAuthentication($provider))($user);
        $user->refresh();

        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
        $this->assertCount(8, $user->recoveryCodes());

        (new ConfirmTwoFactorAuthentication($provider))($user, 'TEST-CODE');

        $this->assertTrue($user->refresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_upload_failure_never_exposes_exception_details(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Active]);
        $service = Mockery::mock(UploadReportsService::class);
        $service->shouldReceive('storeAndImport')
            ->once()
            ->andThrow(new \RuntimeException('private SQL path /var/secrets/import.sql'));
        $this->app->instance(UploadReportsService::class, $service);

        config()->set('app.debug', true);

        $request = UploadReportsRequest::create('/imports/upload', 'POST');
        $request->headers->set('referer', '/imports/upload');
        $request->setContainer($this->app);
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn (): User => $user);
        $response = app(UploadReportsController::class)->store($request, $service);

        $this->assertNotEmpty($response->getTargetUrl());
        $this->assertSame(
            'Laporan gagal diproses. Silakan coba lagi atau hubungi administrator.',
            $response->getSession()->get('error'),
        );
        $this->assertStringNotContainsString('private SQL path', (string) $response->getSession()->get('error'));
    }

    public function test_upload_cleanup_runs_after_successful_import(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $request = $this->uploadRequest($user);
        $orders = Mockery::mock(OrderReportImporter::class);
        $income = Mockery::mock(IncomeReportImporter::class);
        $orders->shouldReceive('import')->once()->andReturn(1);
        $income->shouldReceive('import')->never();

        $result = (new UploadReportsService($orders, $income))->storeAndImport($request, $user);

        $this->assertSame(['orders' => 1, 'income' => 0], $result);
        Storage::disk('local')->assertDirectoryEmpty('reports/orders');
    }

    public function test_upload_cleanup_runs_after_failed_import(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $request = $this->uploadRequest($user);
        $orders = Mockery::mock(OrderReportImporter::class);
        $income = Mockery::mock(IncomeReportImporter::class);
        $orders->shouldReceive('import')->once()->andThrow(new \RuntimeException('import failed'));
        $income->shouldReceive('import')->never();

        $this->expectException(\RuntimeException::class);
        try {
            (new UploadReportsService($orders, $income))->storeAndImport($request, $user);
        } finally {
            Storage::disk('local')->assertDirectoryEmpty('reports/orders');
        }
    }

    public function test_reconciliation_queries_isolate_users_with_identical_identifiers(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $productKey = str_repeat('a', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($userA->id, $productKey, 'A'),
            $this->order($userB->id, $productKey, 'B'),
        ]);
        DB::table('marketplace_income')->insert([
            $this->income($userA->id, $productKey, 'A'),
            $this->income($userB->id, $productKey, 'B'),
        ]);

        $orders = app(MarketplaceReconciliationService::class)
            ->reconciliationPage($userA->id, null, null, ['search' => 'SHARED-ORDER']);
        $income = app(IncomeReconciliationService::class)
            ->page($userA->id, null, null, ['search' => 'SHARED-ORDER', 'per_page' => 25]);

        $this->assertSame(['A'], $orders->getCollection()->pluck('order_product_name')->all());
        $this->assertSame(['A'], $income->getCollection()->pluck('product_name')->all());
    }

    public function test_invalid_reconciliation_filters_are_rejected(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Active]);

        $this->actingAs($user)
            ->get(route('finance.income-reconciliation', [
                'sort_field' => 'users.password',
                'refund_type' => 'anything',
                'statuses' => ['users.password'],
            ]))
            ->assertSessionHasErrors(['sort_field', 'refund_type', 'statuses.0']);
    }

    private function uploadRequest(User $user): Request
    {
        $request = Request::create('/imports/upload', 'POST', [], [], [
            'order_report' => UploadedFile::fake()->create('orders.xlsx', 1, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }

    private function order(int $userId, string $productKey, string $suffix): array
    {
        return [
            'user_id' => $userId,
            'order_number' => 'SHARED-ORDER',
            'order_status' => 'Selesai',
            'tracking_number' => 'TRACK-'.$suffix,
            'product_name' => $suffix,
            'product_key' => $productKey,
            'discounted_price' => 100,
            'unit_price' => 100,
            'quantity' => 1,
            'returned_quantity' => 0,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function income(int $userId, string $productKey, string $suffix): array
    {
        return [
            'user_id' => $userId,
            'order_number' => 'SHARED-ORDER',
            'product_name' => $suffix,
            'product_key' => $productKey,
            'product_price' => 100,
            'quantity' => 1,
            'total_income' => 100,
            'refund_to_buyer' => 0,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
