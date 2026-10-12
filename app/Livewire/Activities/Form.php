<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Services\Tenancy;
use Livewire\Component;

class Form extends Component
{
    public ?Activity $activity = null;
    public string $type = 'call';
    public string $subject = '';
    public string $notes = '';
    public ?string $scheduled_at = null;
    public ?int $user_id = null;

    public function mount(?Activity $activity = null): void
    {
        if ($activity) {
            $this->activity = $activity;
            $this->type = $activity->type;
            $this->subject = $activity->subject;
            $this->notes = $activity->notes ?? '';
            $this->scheduled_at = $activity->scheduled_at?->format('Y-m-d\TH:i');
            $this->user_id = $activity->user_id;
        } else {
            $this->user_id = auth()->id();
            $this->scheduled_at = now()->format('Y-m-d\TH:i');
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'user_id' => ['nullable', app(Tenancy::class)->existsRule('users')],
        ];
    }

    public function save(): void
    {
        $this->validate();
        $data = [
            'type' => $this->type, 'subject' => $this->subject,
            'notes' => $this->notes, 'scheduled_at' => $this->scheduled_at,
            'user_id' => $this->user_id,
        ];
        if ($this->activity->exists) { $this->activity->update($data); session()->flash('success', '活动已更新'); }
        else { Activity::create($data); session()->flash('success', '活动已创建'); }
        $this->redirect(route('activities.index'), navigate: true);
    }

    public function render()
    {
        $users = app(Tenancy::class)->users()->orderBy('name')->get();
        return view('livewire.activities.form', ['users' => $users])
            ->layout('layouts.app', ['title' => ($this->activity ? '编辑' : '新建') . '活动']);
    }
}
