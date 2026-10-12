<?php

namespace App\Livewire\Opportunities;

use App\Models\Opportunity;
use App\Models\Stage;
use Livewire\Component;

class Board extends Component
{
    /**
     * Kanban 看板：商机按阶段分列显示，支持拖拽改阶段。
     *
     * Livewire 4 Sortable 用法：
     * - wire:sortable-group 包裹整个看板区域
     * - wire:sortable-group.item="$opp->id" 在每个卡片上
     * - @sortable-group.updated="onCardMoved($event)" 事件处理
     */

    protected $listeners = ['refresh' => '$refresh'];

    /**
     * 处理卡片拖拽后的事件
     * @param array $payload Livewire sortable-group 传出的 payload，包含 fromStageId, toStageId, itemId
     */
    public function onCardMoved(array $payload): void
    {
        $itemId = $payload['itemId'] ?? null;
        $toStageId = $payload['toStageId'] ?? null;

        if ($itemId && $toStageId) {
            $opp = Opportunity::find($itemId);
            if ($opp && $opp->stage_id != $toStageId) {
                $opp->update(['stage_id' => $toStageId]);
            }
        }

        $this->dispatch('refresh');
    }

    /**
     * 降级方案：直接下拉框选择新阶段
     */
    public function changeStage(int $opportunityId, int $newStageId): void
    {
        $opp = Opportunity::find($opportunityId);
        if ($opp) {
            $opp->update(['stage_id' => $newStageId]);
            session()->flash('success', '商机阶段已更新');
        }
        $this->dispatch('refresh');
    }

    public function render()
    {
        $stages = Stage::ordered()->with('opportunities.owner')->get();

        // 计算总金额
        $totalValue = Opportunity::sum('value');

        return view('livewire.opportunities.board', [
            'stages' => $stages,
            'totalValue' => $totalValue,
        ])->layout('layouts.app', ['title' => '商机看板']);
    }
}
