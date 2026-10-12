<?php

namespace App\Livewire\Attachments;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Services\AuditService;
use App\Services\Tenancy;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 附件面板：可嵌入公司/联系人/商机详情页，支持上传/下载/删除。
 *
 * 多态类型走白名单，防止任意类加载。
 */
class AttachmentPanel extends Component
{
    use WithFileUploads;

    /** 允许挂载附件的模型白名单 */
    public const ALLOWED_TYPES = [
        Company::class,
        Contact::class,
        Opportunity::class,
    ];

    public string $attachableType;
    public int $attachableId;
    public $file;

    public function mount(string $attachableType, int $attachableId): void
    {
        abort_unless(in_array($attachableType, self::ALLOWED_TYPES, true), 404);

        // 目标资源必须在当前租户内存在（业务模型挂 TenantScope，自动过滤）
        $attachableType::findOrFail($attachableId);

        $this->attachableType = $attachableType;
        $this->attachableId = $attachableId;
    }

    /**
     * 上传附件：存本地私有磁盘，记录审计日志。
     */
    public function upload(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);

        $tenant = app(Tenancy::class)->current();
        $originalName = $this->file->getClientOriginalName();
        $extension = $this->file->getClientOriginalExtension() ?: 'bin';
        $path = $this->file->storeAs(
            "attachments/{$tenant->id}/{$this->attachableId}",
            uniqid() . '.' . $extension,
            'local',
        );

        $attachment = Attachment::create([
            'tenant_id' => $tenant->id,
            'attachable_type' => $this->attachableType,
            'attachable_id' => $this->attachableId,
            'original_name' => $originalName,
            'file_path' => $path,
            'mime_type' => $this->file->getMimeType(),
            'size' => $this->file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        AuditService::record($attachment, 'created');

        $this->reset('file');
        session()->flash('attachment_success', __('misc.attachment_uploaded', ['name' => $originalName]));
    }

    /**
     * 删除附件：上传者本人或 admin/manager。
     */
    public function remove(int $attachmentId): void
    {
        $attachment = Attachment::findOrFail($attachmentId);

        $user = auth()->user();
        abort_unless(
            $attachment->uploaded_by === $user->id || $user->canSeeAllRecords(),
            403,
        );

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        session()->flash('attachment_success', __('misc.attachment_deleted', ['name' => $attachment->original_name]));
    }

    public function render()
    {
        $attachments = Attachment::query()
            ->where('attachable_type', $this->attachableType)
            ->where('attachable_id', $this->attachableId)
            ->with('uploader')
            ->latest()
            ->get();

        return view('livewire.attachments.attachment-panel', [
            'attachments' => $attachments,
            'downloadRoute' => 'attachments.download',
        ]);
    }
}
