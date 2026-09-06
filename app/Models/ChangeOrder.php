<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeOrder extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'title', 'description',
        'price_delta', 'days_delta', 'deposit', 'status',
        'submitted_at', 'decided_at', 'client_comment',
    ];

    // Expose 'amount' as alias for price_delta (Flutter model uses 'amount')
    protected $appends = ['amount', 'requested_at', 'approved_at'];

    public function getAmountAttribute(): string
    {
        return number_format((float) ($this->attributes['price_delta'] ?? 0), 2, '.', '');
    }

    public function getRequestedAtAttribute(): ?string
    {
        return $this->submitted_at?->toISOString();
    }

    public function getApprovedAtAttribute(): ?string
    {
        return $this->status === 'approved' ? $this->decided_at?->toISOString() : null;
    }

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'days_delta' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
