<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\StageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    /** @use HasFactory<StageFactory> */
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'order',
        'probability',
        'is_default',
        'is_won',
        'is_lost',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'probability' => 'integer',
            'is_default' => 'boolean',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    /**
     * 赢单阶段范围（供 ReportService / 看板统计复用）。
     */
    public function scopeWon($query)
    {
        return $query->where('is_won', true);
    }

    /**
     * 输单阶段范围。
     */
    public function scopeLost($query)
    {
        return $query->where('is_lost', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}
