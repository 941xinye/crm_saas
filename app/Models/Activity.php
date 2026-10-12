<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'activitable_type',
        'activitable_id',
        'user_id',
        'type',
        'subject',
        'scheduled_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function activitable()
    {
        return $this->morphTo();
    }

    /**
     * 统一多态类型存储：允许 API/导入传 'company' 别名，自动归一化为完整类名。
     */
    public function setActivitableTypeAttribute($value): void
    {
        $this->attributes['activitable_type'] = str_contains($value, '\\')
            ? $value
            : 'App\\Models\\' . ucfirst($value);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
