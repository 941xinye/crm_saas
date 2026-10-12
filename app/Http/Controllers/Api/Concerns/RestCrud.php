<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 通用 REST CRUD 能力：
 * - index: 列表 + 分页 + 搜索
 * - store: 创建（自动注入 tenant_id / audit）
 * - show: 详情（TenantScope 自动隔离）
 * - update: 更新（自动 audit）
 * - destroy: 软删除（自动 audit）
 *
 * 使用方需：
 * 1. 在控制器定义 protected string $model
 * 2. 定义 protected array $rules（create/update 共享或分拆）
 * 3. 可选：$searchFields 搜索字段、$relations eager load、$sortableColumns 排序白名单
 */
trait RestCrud
{
    public function index(Request $request)
    {
        $query = ($this->model)::query();

        // 搜索
        if ($search = $request->input('search')) {
            $fields = $this->searchFields ?? ['name'];
            $query->where(function ($q) use ($search, $fields) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        // 排序（白名单防注入）
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $sortable = $this->sortableColumns ?? ['created_at'];
        if (in_array($sortBy, $sortable, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        // Eager load
        if (! empty($this->relations)) {
            $query->with($this->relations);
        }

        // 自定义字段筛选（cf_key + cf_value；key 仅允许合法标识符，防 JSON path 注入）
        $cfKey = (string) $request->input('cf_key', '');
        if ($cfKey !== '' && preg_match('/^[A-Za-z0-9_]{1,64}$/', $cfKey)) {
            $query->where("custom_fields->{$cfKey}", (string) $request->input('cf_value', ''));
        }

        $perPage = $request->integer('per_page', 15);

        return $this->paginated($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rulesForStore($request));

        // 预留 hook：子类可自动注入默认值（如 Webhook secret）
        if (method_exists($this, 'beforeStore')) {
            $data = $this->beforeStore($request, $data);
        }

        // 数据清洗 hook：子类可过滤/规范化入库数据（如自定义字段只保留已定义 key）
        if (method_exists($this, 'sanitizeData')) {
            $data = $this->sanitizeData($request, $data);
        }

        $modelClass = $this->model;
        $model = new $modelClass($data);
        $model->save();

        if (! empty($this->relations)) {
            $model->load($this->relations);
        }

        return $this->created($model);
    }

    public function show(int $id)
    {
        $modelClass = $this->model;
        $query = $modelClass::query();

        if (! empty($this->relations)) {
            $query->with($this->relations);
        }

        $model = $query->findOrFail($id);

        return $this->success($model);
    }

    public function update(Request $request, int $id)
    {
        $modelClass = $this->model;
        $model = $modelClass::findOrFail($id);

        // 数据权限：sales 仅可更新自己负责的数据
        if (isset($this->ownerColumn)) {
            $user = $request->user();
            if ($user && ! $user->canSeeAllRecords() && $model->{$this->ownerColumn} !== $user->id) {
                abort(403, '无权操作此资源');
            }
        }

        $data = $request->validate($this->rulesForUpdate($request));

        // 数据清洗 hook（与 store 一致）
        if (method_exists($this, 'sanitizeData')) {
            $data = $this->sanitizeData($request, $data);
        }

        $model->update($data);

        if (! empty($this->relations)) {
            $model->load($this->relations);
        }

        return $this->success($model);
    }

    public function destroy(int $id)
    {
        $modelClass = $this->model;
        $model = $modelClass::findOrFail($id);

        // 数据权限
        if (isset($this->ownerColumn)) {
            $user = request()->user();
            if ($user && ! $user->canSeeAllRecords() && $model->{$this->ownerColumn} !== $user->id) {
                abort(403, '无权操作此资源');
            }
        }

        $model->delete();

        return $this->noContent();
    }

    /**
     * 默认 store 规则 = $this->rules，update 规则 = $this->rules 去掉 required + ignore 自身。
     * 子类可覆盖。
     */
    protected function rulesForStore(Request $request): array
    {
        return $this->rules;
    }

    protected function rulesForUpdate(Request $request): array
    {
        return $this->rules;
    }
}
