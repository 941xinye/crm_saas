<?php

namespace Tests\Feature;

use App\Jobs\ImportCsvJob;
use App\Models\Company;
use App\Models\CsvImportJob;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\CsvImportCompletedNotification;
use App\Services\CsvAsyncImport;
use App\Services\CsvService;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * CSV 大文件后台导入（P1）：阈值判定、队列派发、进度记录、完成通知、
 * 失败明细下载（仅发起人本人）。
 */
class CsvAsyncImportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '后台导入测试租户',
            'slug' => 'bi-' . strtolower(\Illuminate\Support\Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);

        config(['crm.import.async_threshold' => 5]);
        Storage::fake('local');
    }

    private function makeCompaniesCsv(array $names): string
    {
        $lines = [__('services.csv_company_name')];
        foreach ($names as $name) {
            $lines[] = $name;
        }

        return "\xEF\xBB\xBF" . implode("\n", $lines);
    }

    /** 联系人 CSV：可构造「非空但校验失败」的行（有邮箱但姓名全空） */
    private function makeContactsCsv(array $rows): string
    {
        $lines = [implode(',', [__('services.csv_contact_last_name'), __('services.csv_contact_first_name'), __('services.csv_email')])];
        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return "\xEF\xBB\xBF" . implode("\n", $lines);
    }

    private function createAndStore(array $names): CsvImportJob
    {
        $content = $this->makeCompaniesCsv($names);

        return app(CsvAsyncImport::class)->dispatch(Company::class, $content, 'companies.csv');
    }

    public function test_threshold_judgement(): void
    {
        $csv = app(CsvService::class);

        $this->assertFalse($csv->exceedsAsyncThreshold($this->makeCompaniesCsv(['甲', '乙', '丙'])));
        $this->assertTrue($csv->exceedsAsyncThreshold($this->makeCompaniesCsv(array_fill(0, 6, '公司'))));

        config(['crm.import.async_threshold' => 0]);
        $this->assertFalse($csv->exceedsAsyncThreshold($this->makeCompaniesCsv(array_fill(0, 100, '公司'))));
    }

    public function test_dispatch_queues_background_job_and_keeps_file(): void
    {
        Bus::fake([ImportCsvJob::class]);

        $record = $this->createAndStore(['公司一', '公司二', '公司三', '公司四', '公司五', '公司六']);

        $this->assertSame('pending', $record->status);
        $this->assertSame(6, $record->total_rows);
        $this->assertNotNull($this->user->id);

        Storage::disk('local')->assertExists($record->path);
        Bus::assertDispatched(ImportCsvJob::class, fn (ImportCsvJob $job) => $job->jobId() === $record->id);
    }

    public function test_background_import_completes_and_notifies(): void
    {
        Bus::fake([ImportCsvJob::class]);
        $record = $this->createAndStore(['公司一', '公司二', '公司三', '公司四', '公司五', '公司六']);

        (new ImportCsvJob($record->id))->handle(app(CsvService::class));

        $record->refresh();
        $this->assertSame('completed', $record->status);
        $this->assertSame(6, $record->created_count);
        $this->assertSame(6, $record->processed_rows);
        $this->assertSame(0, $record->failed_rows);

        $this->assertSame(6, Company::count());
        Storage::disk('local')->assertMissing($record->path);

        $this->assertTrue(
            $this->user->notifications()
                ->where('type', CsvImportCompletedNotification::class)
                ->where('data->type', 'csv_import')
                ->exists()
        );
    }

    public function test_background_import_records_row_errors_and_download_url(): void
    {
        Bus::fake([ImportCsvJob::class]);
        $record = app(CsvAsyncImport::class)->dispatch(
            \App\Models\Contact::class,
            $this->makeContactsCsv([['王', '小明', 'w@example.com'], ['', '', 'bad@example.com']]),
            'contacts.csv',
        );

        (new ImportCsvJob($record->id))->handle(app(CsvService::class));

        $record->refresh();
        $this->assertSame('completed', $record->status);
        $this->assertSame(1, $record->created_count);
        $this->assertSame(1, $record->failed_rows);
        $this->assertArrayHasKey(3, $record->errors); // 第 3 行姓与名均为空

        $payload = $this->user->notifications()
            ->where('type', CsvImportCompletedNotification::class)
            ->latest('id')
            ->first()
            ?->data;

        $this->assertSame(route('csv-imports.errors', ['importJob' => $record->id]), $payload['url'] ?? null);
    }

    public function test_error_report_only_downloadable_by_owner(): void
    {
        Bus::fake([ImportCsvJob::class]);
        $record = app(CsvAsyncImport::class)->dispatch(
            \App\Models\Contact::class,
            $this->makeContactsCsv([['王', '小明', 'w@example.com'], ['', '', 'bad@example.com']]),
            'contacts.csv',
        );
        (new ImportCsvJob($record->id))->handle(app(CsvService::class));
        $record->refresh();

        $response = $this->get(route('csv-imports.errors', ['importJob' => $record->id]));
        $response->assertOk();
        $response->assertSee(__('services.csv_error_row'));

        // 同租户其他成员不可下载
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'sales']);
        $this->actingAs($other);
        $this->get(route('csv-imports.errors', ['importJob' => $record->id]))->assertForbidden();
    }

    public function test_failed_import_marks_status_and_notifies(): void
    {
        // 试用过期触发 CsvService 前置检查 RuntimeException → job 置为 failed 并通知
        $this->tenant->update(['trial_ends_at' => now()->subDay()]);

        $record = $this->createAndStore(['公司一', '公司二']);
        (new ImportCsvJob($record->id))->handle(app(CsvService::class));

        $record->refresh();
        $this->assertSame('failed', $record->status);
        $this->assertSame(1, $record->failed_rows);
        $errors = $record->errors;
        $this->assertStringContainsString(__('services.csv_trial_ended'), (string) reset($errors));
    }
}
