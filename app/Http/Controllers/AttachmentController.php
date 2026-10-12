<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 附件下载：校验登录 + 租户归属后流式返回私有磁盘文件。
 */
class AttachmentController extends Controller
{
    public function download(Attachment $attachment): StreamedResponse
    {
        // Attachment 挂 TenantScope，路由模型绑定已自动限定当前租户，
        // 能拿到实例即代表同租户，租户内成员均可下载。
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_name,
        );
    }
}
