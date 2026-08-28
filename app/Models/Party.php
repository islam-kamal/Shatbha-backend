<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'name',
        'phone',
        'kind',
        'opening_balance',
        'agreement_estimate',
        'supervision_percent',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'agreement_estimate' => 'decimal:2',
            'supervision_percent' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CustomerEntry::class, 'customer_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ContractorJob::class, 'contractor_id');
    }
}
