<?php

namespace Tests\Feature;

use App\Jobs\ImportReportsJob;
use App\Models\ReportImportOperation;
use App\Models\User;
use App\Services\IncomeReportImporter;
use App\Services\OrderReportImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportImportQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_queues_import_and_persists_operation(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('imports.upload.store'), [
            'order_report' => UploadedFile::fake()->create('orders.xlsx', 10),
        ]);

        $response->assertRedirect(route('imports.upload'))
            ->assertSessionHas('success', 'Laporan berhasil masuk ke antrean import.');

        $operation = ReportImportOperation::query()->firstOrFail();
        $this->assertSame('queued', $operation->status);
        $this->assertNotNull($operation->order_path);

        Queue::assertPushed(ImportReportsJob::class, fn (ImportReportsJob $job): bool => $job->operationId === $operation->id);
    }

    public function test_operation_status_is_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $operation = ReportImportOperation::query()->create([
            'user_id' => $other->id,
            'status' => 'processing',
        ]);

        $this->actingAs($user)
            ->getJson(route('imports.upload.status', $operation->id))
            ->assertNotFound();
    }

    public function test_job_imports_files_marks_completed_and_cleans_storage(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Storage::disk('local')->put('reports/orders/orders.xlsx', 'fixture');

        $operation = ReportImportOperation::query()->create([
            'user_id' => $user->id,
            'status' => 'queued',
            'order_path' => 'reports/orders/orders.xlsx',
        ]);

        $orders = $this->mock(OrderReportImporter::class);
        $income = $this->mock(IncomeReportImporter::class);
        $orders->shouldReceive('import')->once()->andReturn(12);
        $income->shouldReceive('import')->never();

        (new ImportReportsJob($operation->id))->handle($orders, $income);

        $operation->refresh();
        $this->assertSame('completed', $operation->status);
        $this->assertSame(12, $operation->orders);
        Storage::disk('local')->assertMissing('reports/orders/orders.xlsx');
    }

    public function test_job_failure_marks_operation_failed_and_hides_exception_details(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Storage::disk('local')->put('reports/orders/orders.xlsx', 'fixture');

        $operation = ReportImportOperation::query()->create([
            'user_id' => $user->id,
            'status' => 'queued',
            'order_path' => 'reports/orders/orders.xlsx',
        ]);

        $orders = $this->mock(OrderReportImporter::class);
        $income = $this->mock(IncomeReportImporter::class);
        $orders->shouldReceive('import')->once()->andThrow(new \RuntimeException('private SQL path'));

        $this->expectException(\RuntimeException::class);

        try {
            (new ImportReportsJob($operation->id))->handle($orders, $income);
        } finally {
            $operation->refresh();
            $this->assertSame('failed', $operation->status);
            $this->assertSame(
                'Laporan gagal diproses. Silakan coba lagi atau hubungi administrator.',
                $operation->error_message,
            );
            $this->assertStringNotContainsString('private SQL path', (string) $operation->error_message);
            Storage::disk('local')->assertMissing('reports/orders/orders.xlsx');
        }
    }
}
