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
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'probability' => 'integer',
            'is_default' => 'boolean',
        ];
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
