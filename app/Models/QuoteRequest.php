<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'vendor_account_id',
        'title',
        'status',
        'notes',
        'contractor_job_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorAccount::class, 'vendor_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }

    public function contractorJob(): BelongsTo
    {
        return $this->belongsTo(ContractorJob::class);
    }

    public function total(): string
    {
        return (string) $this->lines()->selectRaw('SUM(qty * unit_price) as t')->value('t') ?? '0';
    }
}
