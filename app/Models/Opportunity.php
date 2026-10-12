<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory, SoftDeletes, BelongsToTenant, \App\Models\Traits\Auditable;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'company_id',
        'stage_id',
        'owner_id',
        'title',
        'value',
        'expected_close_date',
        'probability',
        'score',
        'scored_at',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'expected_close_date' => 'date',
            'value' => 'decimal:2',
            'probability' => 'integer',
            'score' => 'integer',
            'scored_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    /**
     * 按评分排序：高分优先。
     */
    public function scopeOrderByScore($query, string $direction = 'desc')
    {
        return $query->orderByRaw('score IS NULL')->orderBy('score', $direction);
    }

    /**
     * 分数区间查询（含端点）。
     */
    public function scopeScoreBetween($query, int $min, int $max = null)
    {
        if ($max === null) {
            return $query->where('score', '>=', $min);
        }

        return $query->whereBetween('score', [$min, $max]);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'activitable');
    }
}
