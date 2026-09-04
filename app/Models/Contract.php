<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = [
        'company_id', 'lead_id', 'project_id', 'party_id',
        'title', 'scope_text', 'price', 'payment_terms',
        'start_date', 'expected_completion', 'warranty_months',
        'exclusions', 'change_order_policy', 'status', 'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'start_date' => 'date',
            'expected_completion' => 'date',
            'warranty_months' => 'integer',
            'signed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentInstallment::class);
    }

    // Flutter model reads 'contract_value' and 'start_date', 'end_date'
    protected $appends = ['contract_value', 'end_date'];

    public function getContractValueAttribute(): string
    {
        return number_format((float) ($this->attributes['price'] ?? 0), 2, '.', '');
    }

    public function getEndDateAttribute(): ?string
    {
        return $this->expected_completion?->toDateString();
    }
}
