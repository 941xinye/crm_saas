<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * 租户数据导出/删除（GDPR 合规，仅平台超管触发）。
 * 导出：业务表 + 成员表打包 ZIP（CSV 含 BOM，Excel 直接打开）。
 * 删除：清空业务数据与个人数据；保留审计日志（合规留存）与阶段/Webhook/工作流等租户配置。
 */
class TenantDataService
{
    /** 导出表清单（users 供 GDPR 个人数据核查；审计日志为合规留存记录） */
    private const EXPORT_TABLES = [
        'users', 'companies', 'contacts', 'leads', 'opportunities',
        'activities', 'attachments', 'audit_logs',
    ];

    /**
     * 导出租户全部数据为 ZIP，返回绝对路径。
     */
    public function exportZip(Tenant $tenant): string
    {
        $dir = storage_path('app/tenant-exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zipPath = "{$dir}/tenant_{$tenant->id}_" . now()->format('Ymd_His') . '.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (self::EXPORT_TABLES as $table) {
            $zip->addFromString("{$table}.csv", $this->tableCsv($tenant, $table));
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * 清空租户业务数据与个人数据。
     * 删除顺序：附件（行+物理文件）→ morph 子表（activities）→ 业务子表 → companies → 邀请。
     * 保留：audit_logs（合规）、users 账号、stages / tenant_webhooks / tenant_workflows 配置。
     *
     * @return array<string, int> 各表删除行数
     */
    public function purgeData(Tenant $tenant): array
    {
        $counts = [];

        // 1. 附件：物理文件 + 行（morph 无 FK，必须先清）
        $attachments = DB::table('attachments')->where('tenant_id', $tenant->id)->get();
        foreach ($attachments as $attachment) {
            Storage::disk('local')->delete($attachment->file_path);
        }
        DB::table('attachments')->where('tenant_id', $tenant->id)->delete();
        $counts['attachments'] = $attachments->count();

        // 2. morph 子表
        $counts['activities'] = DB::table('activities')->where('tenant_id', $tenant->id)->delete();

        // 3. 业务表（子 → 父）
        foreach (['opportunities', 'leads', 'contacts', 'companies'] as $table) {
            $counts[$table] = DB::table($table)->where('tenant_id', $tenant->id)->delete();
        }

        // 4. 个人数据：团队邀请（含被邀请人邮箱）
        $counts['tenant_invitations'] = DB::table('tenant_invitations')->where('tenant_id', $tenant->id)->delete();

        return $counts;
    }

    /**
     * 单表导出为 CSV 字符串（BOM + 全量列）。
     * 平台上下文无 Tenancy，直接按 tenant_id 精确过滤。
     */
    private function tableCsv(Tenant $tenant, string $table): string
    {
        $rows = DB::table($table)->where('tenant_id', $tenant->id)->get();

        if ($rows->isEmpty()) {
            return "\xEF\xBB\xBF";
        }

        $columns = array_keys((array) $rows->first());
        $lines = ["\xEF\xBB\xBF" . $this->csvRow($columns)];

        foreach ($rows as $row) {
            $lines[] = $this->csvRow(array_map(
                fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                (array) $row,
            ));
        }

        return implode("\n", $lines);
    }

    private function csvRow(array $fields): string
    {
        return collect($fields)
            ->map(fn ($field) => '"' . str_replace('"', '""', (string) $field) . '"')
            ->implode(',');
    }
}
