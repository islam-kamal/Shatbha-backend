<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerEntry extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'entry_date',
        'entry_type',
        'title',
        'amount',
        'labor_amount',
        'return_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
            'labor_amount' => 'decimal:2',
            'return_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_id');
    }
}
