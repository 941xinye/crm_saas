<?php

namespace App\Livewire\Stages;

use App\Models\Stage;
use App\Services\AuditService;
use Livewire\Component;

/**
 * 商机阶段管理：列表展示 + 内联切换赢/输标记 + 拖拽排序 + CRUD 入口。
 *
 * 阶段是商机流程的核心配置，ReportService 的赢率统计依赖 is_won 标记。
 * 赢/输标记互斥：设 is_won=true 自动清 is_lost，反之亦然。
 */
class Index extends Component
{
    public string $search = '';

    protected $queryString = ['search'];

    public function mount(): void
    {
        $this->authorize('viewAny', Stage::class);
    }

    public function toggleWon(int $id): void
    {
        $stage = Stage::findOrFail($id);
        $this->authorize('update', $stage);

        $stage->update([
            'is_won' => ! $stage->is_won,
            'is_lost' => false, // 赢/输互斥
        ]);

        session()->flash('success', __('stages.won_updated', ['name' => $stage->name]));
    }

    public function toggleLost(int $id): void
    {
        $stage = Stage::findOrFail($id);
        $this->authorize('update', $stage);

        $stage->update([
            'is_lost' => ! $stage->is_lost,
            'is_won' => false, // 赢/输互斥
        ]);

        session()->flash('success', __('stages.lost_updated', ['name' => $stage->name]));
    }

    /**
     * 拖拽排序：更新 order 字段。
     *
     * @param  array{order: array<int, int>}  $payload  Livewire sortable 传出
     */
    public function reorder(array $payload): void
    {
        $user = auth()->user();
        if (! $user || ! in_array($user->role, ['admin', 'manager'])) {
            return;
        }

        $orderMap = $payload['order'] ?? [];
        AuditService::withBatch('updated', Stage::class, function () use ($orderMap) {
            foreach ($orderMap as $position => $stageId) {
                Stage::where('id', $stageId)->update(['order' => ($position + 1) * 10]);
            }
            return count($orderMap);
        });
    }

    public function delete(int $id): void
    {
        $stage = Stage::findOrFail($id);
        $this->authorize('delete', $stage);

        $oppCount = $stage->opportunities()->count();
        if ($oppCount > 0) {
            session()->flash('error', __('stages.delete_blocked', ['name' => $stage->name, 'count' => $oppCount]));
            return;
        }

        $stage->delete();
        session()->flash('success', __('stages.deleted', ['name' => $stage->name]));
    }

    public function render()
    {
        $stages = Stage::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->ordered()
            ->withCount('opportunities')
            ->get();

        return view('livewire.stages.index', [
            'stages' => $stages,
        ])->layout('layouts.app', ['title' => __('stages.title')]);
    }
}
