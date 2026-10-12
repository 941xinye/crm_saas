<?php

namespace App\Http\Controllers\Api;

use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 销售阶段 REST API（只读）。
 * 商机创建必填 stage_id，集成方由此端点发现当前租户可用阶段。
 * Stage 挂 TenantScope，天然租户隔离。
 */
class StageApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Stage::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // 排序（白名单防注入），默认按 order 升序（漏斗顺序）
        $sortBy = $request->input('sort_by', 'order');
        $sortDir = $request->input('sort_dir', 'asc');
        if (in_array($sortBy, ['name', 'order', 'created_at'], true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min($request->integer('per_page', 50), 100);

        return $this->paginated($query->paginate($perPage));
    }
}
