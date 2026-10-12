<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, BelongsToTenant;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
