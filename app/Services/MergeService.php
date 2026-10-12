<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 重复数据合并服务：公司合并 / 联系人合并。
 *
 * 规则：
 * - 源与目标必须同租户（显式校验，防跨租户注入）；
 * - 经 Policy 授权（目标可更新 + 源可删除）；
 * - 事务 + AuditService::withBatch 聚合审计（batch_id 可追溯全部子操作）；
 * - 子记录（联系人/商机/活动/附件）迁移到目标；目标空字段由源回填；
 * - 源记录软删除（可恢复），不物理删除。
 */
class MergeService
{
    // ------------------------------------------------------------------
    // 公司合并
    // ------------------------------------------------------------------

    public function mergeCompanies(User $user, Company $source, Company $target): Company
    {
        $this->ensureSameTenant($source->tenant_id, $target->tenant_id);
        $this->authorizeMerge($user, $source, $target);

        return DB::transaction(function () use ($user, $source, $target) {
            return AuditService::withBatch('merged', Company::class, function () use ($source, $target) {
                $tid = $target->tenant_id;

                // 1. 迁移子记录到目标（显式 tenant_id 条件，不依赖上下文）
                Contact::withoutTenant()
                    ->where('tenant_id', $tid)->where('company_id', $source->id)
                    ->update(['company_id' => $target->id]);

                Opportunity::withoutTenant()
                    ->where('tenant_id', $tid)->where('company_id', $source->id)
                    ->update(['company_id' => $target->id]);

                Activity::withoutTenant()
                    ->where('tenant_id', $tid)
                    ->where('activitable_type', Company::class)->where('activitable_id', $source->id)
                    ->update(['activitable_id' => $target->id]);

                Attachment::withoutTenant()
                    ->where('tenant_id', $tid)
                    ->where('attachable_type', Company::class)->where('attachable_id', $source->id)
                    ->update(['attachable_id' => $target->id]);

                // 2. 目标空字段由源回填（目标已有值不动）
                $backfill = [];
                foreach (['industry', 'website', 'phone', 'address'] as $field) {
                    if (blank($target->{$field}) && ! blank($source->{$field})) {
                        $backfill[$field] = $source->{$field};
                    }
                }

                $mergedCustom = $this->mergeCustomFields($target->custom_fields, $source->custom_fields);
                if ($mergedCustom !== null) {
                    $backfill['custom_fields'] = $mergedCustom;
                }

                if ($backfill !== []) {
                    $target->update($backfill);
                }

                // 3. 源软删除（触发 deleted 审计，可恢复）
                $source->delete();

                return $target->fresh();
            });
        });
    }

    // ------------------------------------------------------------------
    // 联系人合并
    // ------------------------------------------------------------------

    public function mergeContacts(User $user, Contact $source, Contact $target): Contact
    {
        $this->ensureSameTenant($source->tenant_id, $target->tenant_id);
        $this->authorizeMerge($user, $source, $target);

        return DB::transaction(function () use ($source, $target) {
            return AuditService::withBatch('merged', Contact::class, function () use ($source, $target) {
                $tid = $target->tenant_id;

                Opportunity::withoutTenant()
                    ->where('tenant_id', $tid)->where('contact_id', $source->id)
                    ->update(['contact_id' => $target->id]);

                Activity::withoutTenant()
                    ->where('tenant_id', $tid)
                    ->where('activitable_type', Contact::class)->where('activitable_id', $source->id)
                    ->update(['activitable_id' => $target->id]);

                Attachment::withoutTenant()
                    ->where('tenant_id', $tid)
                    ->where('attachable_type', Contact::class)->where('attachable_id', $source->id)
                    ->update(['attachable_id' => $target->id]);

                $backfill = [];
                foreach (['company_id', 'position', 'phone'] as $field) {
                    if (blank($target->{$field}) && ! blank($source->{$field})) {
                        $backfill[$field] = $source->{$field};
                    }
                }

                $mergedCustom = $this->mergeCustomFields($target->custom_fields, $source->custom_fields);
                if ($mergedCustom !== null) {
                    $backfill['custom_fields'] = $mergedCustom;
                }

                if ($backfill !== []) {
                    $target->update($backfill);
                }

                $source->delete();

                return $target->fresh();
            });
        });
    }

    // ------------------------------------------------------------------
    // 内部工具
    // ------------------------------------------------------------------

    private function ensureSameTenant(mixed $sourceTenantId, mixed $targetTenantId): void
    {
        if ((int) $sourceTenantId !== (int) $targetTenantId) {
            throw ValidationException::withMessages([
                'merge' => __('merge.same_tenant_required'),
            ]);
        }
    }

    /**
     * 合并授权：目标可更新 + 源可删除（对应各自 Policy 的 delete 语义）。
     */
    private function authorizeMerge(User $user, Company|Contact $source, Company|Contact $target): void
    {
        if (! $user->can('update', $target) || ! $user->can('delete', $source)) {
            throw ValidationException::withMessages([
                'merge' => __('merge.no_permission'),
            ]);
        }
    }

    /**
     * 自定义字段回填：目标缺失/为空的键取源的值；目标已有值不动。
     * 两边皆空时返回 null（不更新）。
     */
    private function mergeCustomFields(?array $targetFields, ?array $sourceFields): ?array
    {
        if (empty($sourceFields)) {
            return null;
        }

        $merged = $targetFields ?? [];
        $changed = false;
        foreach ($sourceFields as $key => $value) {
            if (blank($value)) {
                continue;
            }
            if (! array_key_exists($key, $merged) || blank($merged[$key])) {
                $merged[$key] = $value;
                $changed = true;
            }
        }

        return $changed ? $merged : null;
    }
}
