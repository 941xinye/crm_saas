<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CSV 后台导入任务（进度表）：
 * 大文件导入转入队列后的进度与结果记录，TenantScope 隔离，
 * 失败明细仅发起人本人可下载（见 CsvImportController）。
 */
class CsvImportJob extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'resource', 'original_name', 'path', 'status',
        'total_rows', 'processed_rows', 'created_count',
        'skipped_rows', 'duplicate_rows', 'failed_rows', 'errors',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
