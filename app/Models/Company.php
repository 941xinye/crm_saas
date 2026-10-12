<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'owner_id',
        'name',
        'industry',
        'website',
        'phone',
        'address',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}
