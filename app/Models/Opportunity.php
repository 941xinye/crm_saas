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
    use HasFactory, SoftDeletes, BelongsToTenant;

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
    ];

    protected function casts(): array
    {
        return [
            'expected_close_date' => 'date',
            'value' => 'decimal:2',
            'probability' => 'integer',
        ];
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
