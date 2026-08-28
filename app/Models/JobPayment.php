<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPayment extends Model
{
    protected $fillable = [
        'job_id',
        'sequence',
        'amount',
        'paid_on',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ContractorJob::class, 'job_id');
    }
}
