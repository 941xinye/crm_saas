<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Services\MergeService;
use Livewire\Component;

/**
 * 联系人重复项合并：在详情页选择同名重复联系人，将其数据并入当前联系人。
 * 注：邮箱已有 (tenant_id, email) 唯一约束，姓名才是实际重复维度。
 */
class Merge extends Component
{
    public Contact $contact;

    /** 待合并（源）联系人 ID */
    public ?int $sourceId = null;

    public bool $confirming = false;

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
    }

    public function confirm(): void
    {
        $this->validate([
            'sourceId' => ['required', 'integer'],
        ]);
        $this->confirming = true;
    }

    public function cancel(): void
    {
        $this->confirming = false;
        $this->sourceId = null;
    }

    public function merge(MergeService $merger): void
    {
        $source = Contact::find($this->sourceId);

        if (! $source || $source->id === $this->contact->id) {
            $this->addError('sourceId', __('merge.contact_select_required'));
            return;
        }

        try {
            $merger->mergeContacts(auth()->user(), $source, $this->contact);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors()['merge'] ?? [] as $message) {
                $this->addError('sourceId', $message);
            }
            $this->confirming = false;

            return;
        }

        session()->flash('success', __('merge.contact_merged'));
        $this->redirect(route('contacts.show', $this->contact), navigate: true);
    }

    public function render()
    {
        // 候选：同名其他联系人（邮箱有 UNIQUE 约束不会重复，姓名才是常见重复维度）
        $candidates = Contact::where('first_name', $this->contact->first_name)
            ->where('last_name', $this->contact->last_name)
            ->where('id', '!=', $this->contact->id)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('livewire.contacts.merge', [
            'candidates' => $candidates,
        ]);
    }
}
