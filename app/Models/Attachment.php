<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * 附件：多态关联公司/联系人/商机等资源，文件存本地私有磁盘。
 */
class Attachment extends Model
{
    use BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'attachable_type',
        'attachable_id',
        'original_name',
        'file_path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * 人类可读的文件大小。
     */
    public function readableSize(): string
    {
        $bytes = (int) $this->size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
