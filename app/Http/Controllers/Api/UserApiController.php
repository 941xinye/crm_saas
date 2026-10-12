<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 租户成员 REST API（只读）。
 * 供集成方在分配 owner_id 时发现当前租户内的成员。
 * 仅暴露 id / name / role，不泄露邮箱等敏感信息。
 */
class UserApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        // User 不挂 TenantScope，显式按当前租户过滤
        $query = User::query()
            ->where('tenant_id', app(Tenancy::class)->id())
            ->select('id', 'name', 'role', 'created_at');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // 排序（白名单防注入）
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');
        if (in_array($sortBy, ['name', 'role', 'created_at'], true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min($request->integer('per_page', 50), 100);

        return $this->paginated($query->paginate($perPage));
    }
}
