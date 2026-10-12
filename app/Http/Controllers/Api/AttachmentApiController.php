<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Services\AuditService;
use App\Services\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * 附件 REST API — 列表查询、上传（multipart）与下载。
 * Attachment 挂 TenantScope，天然租户隔离。
 */
class AttachmentApiController extends ApiController
{
    /** 允许查询的多态类型 => 模型类 */
    private const MORPHS = [
        'company' => Company::class,
        'contact' => Contact::class,
        'opportunity' => Opportunity::class,
    ];

    /**
     * 附件列表。可选过滤：attachable_type (company|contact|opportunity)、attachable_id。
     * GET /api/v1/attachments
     */
    public function index(Request $request)
    {
        $request->validate([
            'attachable_type' => ['nullable', 'in:' . implode(',', array_keys(self::MORPHS))],
            'attachable_id' => ['nullable', 'integer'],
        ]);

        $query = Attachment::query()->with('uploader:id,name');

        if ($type = $request->input('attachable_type')) {
            $query->where('attachable_type', self::MORPHS[$type]);
        }
        if ($id = $request->input('attachable_id')) {
            $query->where('attachable_id', (int) $id);
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return $this->paginated($query->orderByDesc('created_at')->paginate($perPage));
    }

    /**
     * 附件详情。
     * GET /api/v1/attachments/{id}
     */
    public function show(Attachment $attachment)
    {
        return $this->success($attachment->load('uploader:id,name'));
    }

    /**
     * 附件上传（multipart/form-data）。
     * POST /api/v1/attachments
     * Body: file + attachable_type (company|contact|opportunity) + attachable_id
     *
     * 与 Web 端 AttachmentPanel 同一套校验：10MB 限制 + 类型白名单，
     * 目标资源必须属于当前租户（TenantScope 过滤，跨租户 404）。
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt,zip'],
            'attachable_type' => ['required', 'in:' . implode(',', array_keys(self::MORPHS))],
            'attachable_id' => ['required', 'integer'],
        ]);

        $morphClass = self::MORPHS[$data['attachable_type']];
        $target = $morphClass::findOrFail($data['attachable_id']);

        $tenant = app(Tenancy::class)->current();
        $file = $data['file'];
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $path = $file->storeAs(
            "attachments/{$tenant->id}/{$target->id}",
            uniqid() . '.' . $extension,
            'local',
        );

        $attachment = Attachment::create([
            'tenant_id' => $tenant->id,
            'attachable_type' => $morphClass,
            'attachable_id' => $target->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        AuditService::record($attachment, 'created');

        return $this->created($attachment->load('uploader:id,name'));
    }

    /**
     * 附件下载（流式返回私有磁盘文件）。
     * GET /api/v1/attachments/{id}/download
     */
    public function download(Attachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
}
