<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'owner_id',
        'name',
        'company_name',
        'email',
        'phone',
        'source',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
