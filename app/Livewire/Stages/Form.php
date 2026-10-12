<?php

namespace App\Livewire\Stages;

use App\Models\Stage;
use Livewire\Component;

/**
 * 新建/编辑商机阶段。
 */
class Form extends Component
{
    public ?Stage $stage = null;
    public string $name = '';
    public int $order = 0;
    public int $probability = 0;
    public bool $is_won = false;
    public bool $is_lost = false;
    public bool $is_default = false;

    public function mount(?Stage $stage = null): void
    {
        $this->stage = $stage ?? new Stage();
        $this->authorize($stage ? 'update' : 'create', $this->stage);

        if ($stage) {
            $this->name = $stage->name;
            $this->order = $stage->order;
            $this->probability = $stage->probability;
            $this->is_won = $stage->is_won;
            $this->is_lost = $stage->is_lost;
            $this->is_default = $stage->is_default;
        } else {
            // 新建时 order 自动排到最后
            $this->order = (Stage::max('order') ?? 0) + 10;
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'order' => ['required', 'integer', 'min:0'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'is_won' => ['boolean'],
            'is_lost' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * 赢/输标记互斥切换。
     */
    public function updatedIsWon(): void
    {
        if ($this->is_won) {
            $this->is_lost = false;
        }
    }

    public function updatedIsLost(): void
    {
        if ($this->is_lost) {
            $this->is_won = false;
        }
    }

    public function save(): void
    {
        $this->validate();

        // 再次确保赢/输互斥
        if ($this->is_won && $this->is_lost) {
            $this->is_lost = false;
        }

        $data = [
            'name' => $this->name,
            'order' => $this->order,
            'probability' => $this->probability,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
            'is_default' => $this->is_default,
        ];

        if ($this->stage->exists) {
            $this->stage->update($data);
            session()->flash('success', __('stages.updated'));
        } else {
            Stage::create($data);
            session()->flash('success', __('stages.created'));
        }

        $this->redirect(route('stages.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.stages.form')
            ->layout('layouts.app', ['title' => $this->stage->exists ? __('stages.edit_title') : __('stages.create_title')]);
    }
}
