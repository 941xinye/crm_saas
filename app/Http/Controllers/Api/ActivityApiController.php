<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RestCrud;
use App\Models\Activity;
use App\Services\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 活动 REST API — 跟进活动 CRUD，多态关联公司/联系人/商机。
 */
class ActivityApiController extends ApiController
{
    use RestCrud;

    protected string $model = Activity::class;

    protected array $relations = ['user'];

    protected array $searchFields = ['subject', 'notes'];

    protected array $sortableColumns = ['subject', 'type', 'scheduled_at', 'created_at'];

    protected ?string $ownerColumn = 'user_id'; // sales 仅可操作自己记录的活动

    /** 多态关联允许的类型（白名单防任意模型注入） */
    private const MORPH_TYPES = ['company' => 'company', 'contact' => 'contact', 'opportunity' => 'opportunity'];

    /** 多态关联必填（activities 表 NOT NULL）；类型白名单防任意模型注入，ID 校验租户归属防跨租户注入 */
    private function morphRules(Request $request, bool $required): array
    {
        $tenantId = app(Tenancy::class)->id();

        $existsInTenant = function (string $attribute, $value, \Closure $fail) use ($request, $tenantId) {
            $type = $request->input('activitable_type');
            $modelClass = self::MORPH_TYPES[$type] ?? null;
            if ($modelClass === null) {
                $fail('activitable_type 必须是 company、contact 或 opportunity 之一');

                return;
            }

            $fullClass = 'App\\Models\\' . ucfirst($modelClass);
            $exists = $fullClass::withoutTenant()
                ->where('id', $value)
                ->where('tenant_id', $tenantId)
                ->exists();
            if (! $exists) {
                $fail('关联对象不存在或不属于当前租户');
            }
        };

        return [
            'activitable_type' => $required
                ? ['required', 'string', 'in:company,contact,opportunity']
                : ['nullable', 'required_with:activitable_id', 'string', 'in:company,contact,opportunity'],
            'activitable_id' => $required
                ? ['required', 'integer', $existsInTenant]
                : ['nullable', 'required_with:activitable_type', 'integer', $existsInTenant],
        ];
    }

    /**
     * user_id 未传时默认当前用户（activities.user_id 为 NOT NULL）。
     */
    protected function beforeStore(Request $request, array $data): array
    {
        $data['user_id'] ??= $request->user()?->id;

        return $data;
    }

    protected function rulesForStore(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return array_merge($this->morphRules($request, true), [
            'type' => ['required', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ]);
    }

    protected function rulesForUpdate(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return array_merge($this->morphRules($request, false), [
            'type' => ['sometimes', 'string', 'max:50'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ]);
    }
}
