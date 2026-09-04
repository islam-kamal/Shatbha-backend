<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    protected $fillable = [
        'company_id', 'lead_id', 'title', 'estimated_cost',
        'selling_price', 'markup_pct', 'scope_json', 'status', 'sent_at',
    ];

    // Flutter model reads 'total_amount' and 'amount'
    protected $appends = ['total_amount', 'amount'];

    public function getTotalAmountAttribute(): string
    {
        return number_format((float) ($this->attributes['selling_price'] ?? 0), 2, '.', '');
    }

    public function getAmountAttribute(): string
    {
        return $this->total_amount;
    }

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'markup_pct' => 'decimal:2',
            'scope_json' => 'array',
            'sent_at' => 'datetime',
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
}
