<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * CSV 导入导出服务：
 * - 导出：UTF-8 BOM（Windows Excel 中文兼容），列配置单一来源
 * - 导入：过滤 tenant_id 防注入、逐行 ensureCanCreate、行级错误收集
 * - 查重：Company 按名称 / Contact 按邮箱，重复行跳过并计数（不阻塞其余行）
 *
 * 模型需实现静态方法 csvColumns() 返回 [header => db_column] 映射。
 */
class CsvService
{
    /** BOM：Windows Excel 打开中文 CSV 不乱码 */
    private const BOM = "\xEF\xBB\xBF";

    /** 模型类 => [services 语言键 => db_column]，运行时经 __() 解析为 [表头 => db_column]（见 columns()） */
    private const COLUMN_KEYS = [
        Company::class => [
            'csv_company_name' => 'name',
            'csv_company_industry' => 'industry',
            'csv_company_website' => 'website',
            'csv_phone' => 'phone',
            'csv_company_address' => 'address',
        ],
        Contact::class => [
            'csv_contact_last_name' => 'last_name',
            'csv_contact_first_name' => 'first_name',
            'csv_email' => 'email',
            'csv_phone' => 'phone',
            'csv_contact_position' => 'position',
            'csv_contact_company' => 'company_name',   // 导入时按名称匹配已有公司或自动新建
        ],
        Lead::class => [
            'csv_lead_name' => 'name',
            'csv_lead_company_name' => 'company_name',
            'csv_email' => 'email',
            'csv_phone' => 'phone',
            'csv_lead_source' => 'source',
            'csv_lead_status' => 'status',
        ],
        Opportunity::class => [
            'csv_opp_title' => 'title',
            'csv_opp_value' => 'value',
            'csv_opp_close_date' => 'expected_close_date',
            'csv_opp_stage' => 'stage_name',           // 导入时按名称匹配 Stage
            'csv_contact_company' => 'company_name',   // 导入时按名称匹配 Company
            'csv_opp_contact' => 'contact_name',       // 导入时按全名匹配 Contact
        ],
        Activity::class => [
            'csv_activity_type' => 'type',
            'csv_activity_subject' => 'subject',
            'csv_activity_scheduled_at' => 'scheduled_at',
            'csv_activity_completed_at' => 'completed_at',
            'csv_activity_user' => 'user_name',        // 导入时按姓名匹配 User
            'csv_activity_notes' => 'notes',
        ],
    ];

    /** 模型类 => [翻译后表头 => db_column]，同时作为导入字段白名单（zh_CN 表头与历史中文表头逐字一致） */
    private function columns(string $modelClass): array
    {
        $keys = self::COLUMN_KEYS[$modelClass] ?? throw new \InvalidArgumentException("未配置 CSV 列：{$modelClass}");

        $columns = [];
        foreach ($keys as $key => $dbColumn) {
            $columns[__("services.{$key}")] = $dbColumn;
        }

        return $columns;
    }

    /** 导入时从 CSV 行中剥离的敏感字段（防跨租户注入）。status 是业务字段，允许覆盖 */
    private const STRIP_FIELDS = ['tenant_id', 'owner_id', 'user_id', 'stage_id', 'company_id', 'contact_id', 'activitable_type', 'activitable_id'];

    // ------------------------------------------------------------------
    // 导出
    // ------------------------------------------------------------------

    /**
     * 导出当前租户下指定模型的所有记录为 CSV 字符串（含 BOM）。
     *
     * @param  class-string  $modelClass
     */
    public function export(string $modelClass, ?Builder $query = null): string
    {
        $columns = $this->columns($modelClass);
        $header = array_keys($columns);

        $query = $query ?? $modelClass::query();
        $rows = $query->get();

        $lines = [];
        $lines[] = $this->csvLine($header);

        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $headerKey => $dbColumn) {
                $values[] = $this->exportValue($row, $dbColumn, $modelClass);
            }
            $lines[] = $this->csvLine($values);
        }

        return self::BOM . implode("\n", $lines);
    }

    private function exportValue(Model $row, string $dbColumn, string $modelClass): string
    {
        // Contact 的 company_name 关联 Company::name
        if ($modelClass === Contact::class && $dbColumn === 'company_name') {
            return (string) ($row->company?->name ?? '');
        }
        // Opportunity 的 company_name / contact_name / stage_name
        if ($modelClass === Opportunity::class) {
            if ($dbColumn === 'company_name') {
                return (string) ($row->company?->name ?? '');
            }
            if ($dbColumn === 'contact_name') {
                return (string) ($row->contact?->full_name ?? '');
            }
            if ($dbColumn === 'stage_name') {
                return (string) ($row->stage?->name ?? '');
            }
        }
        // Activity 的 user_name 关联 User::name
        if ($modelClass === Activity::class && $dbColumn === 'user_name') {
            return (string) ($row->user?->name ?? '');
        }

        $value = $row->$dbColumn;
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return (string) $value;
    }

    /**
     * 生成 HTTP 下载响应。
     */
    public function download(string $modelClass, string $filename, ?Builder $query = null)
    {
        return response($this->export($modelClass, $query))
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * 导出报表（关联数组行集，首行为列名）为下载响应。
     * 供 Reports 页各数据集（趋势/业绩/阶段/漏斗）复用。
     */
    public function downloadReport(string $filename, array $rows)
    {
        $lines = [];

        if (! empty($rows)) {
            $lines[] = $this->csvLine(array_keys($rows[0]));
            foreach ($rows as $row) {
                $lines[] = $this->csvLine(array_values($row));
            }
        } else {
            $lines[] = $this->csvLine([__('services.csv_report_hint')]);
            $lines[] = $this->csvLine([__('services.csv_no_data')]);
        }

        return response(self::BOM . implode("\n", $lines))
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * 导出审计日志为 CSV 字符串（含 BOM）。
     * 查询需自带过滤条件（Web 请求下 AuditLog 挂 TenantScope 自动按租户过滤）。
     */
    public function exportAuditLogs(Builder $query): string
    {
        $lines = [];
        $lines[] = $this->csvLine(['ID', __('services.csv_audit_time'), __('services.csv_audit_operator'), __('services.csv_audit_action'), __('services.csv_audit_object_type'), __('services.csv_audit_object_id'), 'Batch ID', 'IP', 'Route', __('services.csv_audit_before'), __('services.csv_audit_after')]);

        $query
            ->with(['user:id,name'])
            ->chunkById(500, function ($logs) use (&$lines) {
                foreach ($logs as $log) {
                    $lines[] = $this->csvLine([
                        $log->id,
                        $log->created_at?->format('Y-m-d H:i:s') ?? '',
                        $log->user?->name ?? '',
                        $log->action,
                        class_basename((string) $log->auditable_type),
                        $log->auditable_id,
                        $log->batch_id ?? '',
                        $log->ip ?? '',
                        $log->route ?? '',
                        $log->before ? $this->singleLineJson($log->before) : '',
                        $log->after ? $this->singleLineJson($log->after) : '',
                    ]);
                }
            });

        return self::BOM . implode("\n", $lines);
    }

    /**
     * 审计日志 HTTP 下载响应。
     */
    public function downloadAuditLogs(Builder $query, ?string $filename = null)
    {
        $filename ??= __('services.csv_audit_log_filename');

        return response($this->exportAuditLogs($query))
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * JSON 值压成单行（diff 字段含换行会破坏 CSV 行结构）。
     */
    private function singleLineJson(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);

        return str_replace(["\r\n", "\r", "\n"], ' ', (string) $json);
    }

    /**
     * 解析 CSV 文件内容为记录数组，同时返回错误摘要。
     *
     * @return array{created: int, errors: array<int, string>}
     */
    public function import(
        string $modelClass,
        string $csvContent,
        ?\App\Models\Tenant $tenant = null,
        ?callable $onProgress = null,
    ): array {
        $columns = $this->columns($modelClass);
        $tenant = $tenant ?? app(Tenancy::class)->current();
        $planService = app(PlanService::class);

        // 试用期前置检查：过期直接拒整个批次（避免逐行报同一个错）
        if ($tenant && ! $planService->canCreate($tenant)) {
            throw new \RuntimeException(__('services.csv_trial_ended'));
        }

        // 配额前置检查：CSV 行数 + 当前用量 > 配额时拒整个批次
        $targetResource = $this->resourceKey($modelClass);
        if ($tenant && $targetResource) {
            $quota = $planService->quota($tenant, $targetResource);
            if ($quota >= 0) {
                $count = $this->countCsvRows($csvContent);
                $usage = $planService->usage($tenant, $targetResource);
                if ($usage + $count > $quota) {
                    throw new \RuntimeException(__('services.csv_quota_insufficient', [
                        'usage' => $usage, 'quota' => $quota, 'count' => $count,
                    ]));
                }
            }
        }

        $lines = explode("\n", $csvContent);
        // 跳过 BOM
        if (! empty($lines) && str_starts_with($lines[0], self::BOM)) {
            $lines[0] = substr($lines[0], strlen(self::BOM));
        }
        if (empty($lines) || trim($lines[0]) === '') {
            return ['created' => 0, 'errors' => [__('services.csv_empty_file')]];
        }

        $headers = array_map('trim', str_getcsv($lines[0]));
        $headerToColumn = $this->headerToColumnMap($headers, $columns);

        // 表头有效性检查：只校验必需列（模型必填字段）
        $requiredHeaders = match ($modelClass) {
            Company::class => [__('services.csv_company_name')],
            Contact::class => [], // 姓或名至少一项，行级校验
            Lead::class => [__('services.csv_lead_name')],
            Opportunity::class => [__('services.csv_opp_title'), __('services.csv_opp_stage')],
            Activity::class => [__('services.csv_activity_type'), __('services.csv_activity_subject')],
            default => [],
        };
        $presentHeaders = array_keys($headerToColumn);
        $missing = array_values(array_diff($requiredHeaders, $presentHeaders));
        if ($modelClass === Opportunity::class) {
            // 商机的阶段/公司/联系人按名称解析而非 fillable 列
            $missing = array_values(array_diff($missing, [__('services.csv_opp_stage'), __('services.csv_contact_company'), __('services.csv_opp_contact')]));
        }
        if (! empty($missing)) {
            return ['created' => 0, 'errors' => [__('services.csv_missing_columns', ['columns' => implode(__('services.csv_list_separator'), $missing)])]];
        }

        $created = 0;
        $errors = [];
        $skipped = 0;
        $duplicates = 0;

        foreach (array_slice($lines, 1) as $lineNum => $line) {
            $rowNum = $lineNum + 2; // 含表头行号
            $row = str_getcsv($line);
            // 全空行跳过
            if (count(array_filter(array_map('trim', $row))) === 0) {
                $skipped++;
                continue;
            }

            // 进度回调（后台导入按行回写 processed_rows，见 ImportCsvJob）
            if ($onProgress !== null) {
                ($onProgress)($rowNum);
            }

            try {
                $data = [];
                foreach ($headerToColumn as $header => $column) {
                    $idx = array_search($header, $headers, true);
                    $data[$column] = isset($row[$idx]) ? trim($row[$idx]) : '';
                }

                // 过滤敏感字段（CSV 不得覆盖 tenant_id 等）
                foreach (self::STRIP_FIELDS as $strip) {
                    unset($data[$strip]);
                }

                // 关联名称 → ID 解析
                $data = $this->resolveRelations($modelClass, $data, $tenant);

                // 必填字段检查
                $validation = $this->validateRow($modelClass, $data);
                if ($validation !== null) {
                    $errors[$rowNum] = $validation;
                    continue;
                }

                // 查重（Company 按名称 / Contact 按邮箱）：重复行跳过计数，覆盖同文件前序已导入行
                $duplicate = $this->duplicateError($modelClass, $data);
                if ($duplicate !== null) {
                    $duplicates++;
                    continue;
                }

                // 配额二次检查（前置可能漏算已删除/软删记录）
                if ($tenant && $targetResource) {
                    $planService->ensureCanCreate($tenant, $targetResource);
                }

                $modelClass::create($data);
                $created++;
            } catch (\Throwable $e) {
                $errors[$rowNum] = $e->getMessage();
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'duplicates' => $duplicates, 'errors' => $errors];
    }

    /**
     * 行级查重（租户内）：返回提示消息或 null（非重复）。
     * Company 按名称精确匹配；Contact 按邮箱精确匹配（邮箱为空不查）。
     */
    private function duplicateError(string $modelClass, array $data): ?string
    {
        return match ($modelClass) {
            Company::class => isset($data['name']) && $data['name'] !== ''
                && Company::where('name', $data['name'])->exists()
                ? __('services.csv_duplicate_company', ['name' => $data['name']]) : null,
            Contact::class => ! empty($data['email'])
                && Contact::where('email', $data['email'])->exists()
                ? __('services.csv_duplicate_contact', ['email' => $data['email']]) : null,
            default => null,
        };
    }

    /**
     * 导入时把 company_name/stage_name 等名称解析为 ID，并从 data 中移除。
     */
    private function resolveRelations(string $modelClass, array $data, ?\App\Models\Tenant $tenant): array
    {
        if ($modelClass === Contact::class) {
            if (! empty($data['company_name'])) {
                $company = Company::where('name', $data['company_name'])->first();
                if ($company) {
                    $data['company_id'] = $company->id;
                }
            }
            unset($data['company_name']);
        }

        if ($modelClass === Opportunity::class) {
            if (! empty($data['company_name'])) {
                $company = Company::where('name', $data['company_name'])->first();
                if ($company) {
                    $data['company_id'] = $company->id;
                }
            }
            unset($data['company_name']);

            if (! empty($data['stage_name'])) {
                $stage = \App\Models\Stage::where('name', $data['stage_name'])->first();
                if (! $stage) {
                    throw new \RuntimeException(__('services.csv_stage_not_found', ['name' => $data['stage_name']]));
                }
                $data['stage_id'] = $stage->id;
            }
            unset($data['stage_name']);

            if (! empty($data['contact_name'])) {
                [$first, $last] = $this->splitFullName($data['contact_name']);
                $contact = Contact::where('first_name', $first)->where('last_name', $last)->first();
                if (! $contact) {
                    // 尝试模糊匹配
                    $like = '%' . $data['contact_name'] . '%';
                    $contact = Contact::whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])->first();
                }
                if ($contact) {
                    $data['contact_id'] = $contact->id;
                }
            }
            unset($data['contact_name']);

            // 金额/日期类型转换
            if (isset($data['value']) && $data['value'] !== '') {
                $data['value'] = (float) $data['value'];
            }
            if (isset($data['expected_close_date']) && $data['expected_close_date'] !== '') {
                $data['expected_close_date'] = \Carbon\Carbon::parse($data['expected_close_date'])->toDateString();
            }
        }

        if ($modelClass === Activity::class) {
            // 负责人姓名 → user_id（按租户隔离查找）
            if (! empty($data['user_name'])) {
                $user = \App\Models\User::query()
                    ->where('tenant_id', $tenant?->id ?? app(Tenancy::class)->id())
                    ->where('name', $data['user_name'])
                    ->first();
                if (! $user) {
                    throw new \RuntimeException(__('services.csv_owner_not_found', ['name' => $data['user_name']]));
                }
                $data['user_id'] = $user->id;
            }
            unset($data['user_name']);

            // 日期类型转换
            if (isset($data['scheduled_at']) && $data['scheduled_at'] !== '') {
                $data['scheduled_at'] = \Carbon\Carbon::parse($data['scheduled_at'])->toDateTimeString();
            }
            if (isset($data['completed_at']) && $data['completed_at'] !== '') {
                $data['completed_at'] = \Carbon\Carbon::parse($data['completed_at'])->toDateTimeString();
            }
        }

        return $data;
    }

    private function splitFullName(string $fullName): array
    {
        $name = trim($fullName);
        if ($name === '') {
            return ['', ''];
        }
        if (str_contains($name, ' ')) {
            [$first, $rest] = explode(' ', $name, 2);
            return [trim($first), trim($rest)]; // [first_name, last_name]
        }
        // 中式：首字为姓，其余为名
        return [mb_substr($name, 1), mb_substr($name, 0, 1)]; // [first_name, last_name]
    }

    /** 行级必填字段验证，返回错误消息或 null */
    private function validateRow(string $modelClass, array $data): ?string
    {
        return match ($modelClass) {
            Company::class => empty($data['name']) ? __('services.csv_company_name_required') : null,
            Contact::class => (empty($data['last_name']) && empty($data['first_name'])) ? __('services.csv_contact_name_required') : null,
            Lead::class => empty($data['name']) ? __('services.csv_lead_name_required') : null,
            Opportunity::class => empty($data['stage_id']) || empty($data['title']) ? __('services.csv_opp_required') : null,
            Activity::class => empty($data['subject']) ? __('services.csv_activity_subject_required') : (empty($data['type']) ? __('services.csv_activity_type_required') : null),
            default => null,
        };
    }

    // ------------------------------------------------------------------
    // 工具
    // ------------------------------------------------------------------

    private function csvLine(array $values): string
    {
        return implode(',', array_map(function ($v) {
            $v = str_replace('"', '""', (string) $v);
            foreach ([',', '"', "\n", "\r"] as $sep) {
                if (str_contains($v, $sep)) {
                    return "\"{$v}\"";
                }
            }
            return $v;
        }, $values));
    }

    private function headerToColumnMap(array $headers, array $columns): array
    {
        $map = [];
        foreach ($headers as $header) {
            $header = trim($header);
            if (isset($columns[$header])) {
                $map[$header] = $columns[$header];
            }
        }
        return $map;
    }

    private function countCsvRows(string $csvContent): int
    {
        $lines = explode("\n", $csvContent);
        if (! empty($lines) && str_starts_with($lines[0], self::BOM)) {
            $lines[0] = substr($lines[0], strlen(self::BOM));
        }
        return max(0, count(array_filter(array_map('trim', $lines))) - 1);
    }

    /** 行数统计（后台导入进度表用） */
    public function countRows(string $csvContent): int
    {
        return $this->countCsvRows($csvContent);
    }

    /**
     * 是否超过后台导入阈值：超过则应转队列处理（ImportCsvJob），
     * 0 表示全部同步。见 docs/csv.md。
     */
    public function exceedsAsyncThreshold(string $csvContent): bool
    {
        $threshold = (int) config('crm.import.async_threshold', 200);

        return $threshold > 0 && $this->countCsvRows($csvContent) > $threshold;
    }

    /** 模型类 => PlanService 资源键 */
    private function resourceKey(string $modelClass): ?string
    {
        return match ($modelClass) {
            Company::class => 'companies',
            Contact::class => 'contacts',
            Lead::class => 'leads',
            Opportunity::class => 'opportunities',
            default => null, // Activity 暂无配额限制
        };
    }

    /**
     * 生成模板 CSV（空数据，只有表头）。
     */
    public function template(string $modelClass): string
    {
        $columns = $this->columns($modelClass);
        return self::BOM . implode(',', array_keys($columns));
    }

    public function templateDownload(string $modelClass, string $filename)
    {
        return response($this->template($modelClass))
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
