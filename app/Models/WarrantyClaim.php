<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaim extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'category', 'title', 'description',
        'priority', 'status', 'assigned_vendor_id', 'visit_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'visit_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function assignedVendor(): BelongsTo
    {
        return $this->belongsTo(VendorAccount::class, 'assigned_vendor_id');
    }
}
