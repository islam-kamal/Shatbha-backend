<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractorJob extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'contractor_id',
        'title',
        'qty',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(JobPayment::class, 'job_id');
    }

    public function totalAmount(): string
    {
        return bcmul((string) $this->qty, (string) $this->unit_price, 2);
    }

    public function paidAmount(): string
    {
        return (string) $this->payments()->sum('amount');
    }

    public function remaining(): string
    {
        return bcsub($this->totalAmount(), $this->paidAmount(), 2);
    }
}
