<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'vendor_account_id',
        'status',
        'ordered_on',
        'expected_delivery_on',
        'actual_delivery_on',
    ];

    protected function casts(): array
    {
        return [
            'ordered_on' => 'date',
            'expected_delivery_on' => 'date',
            'actual_delivery_on' => 'date',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorAccount::class, 'vendor_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PoLine::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
