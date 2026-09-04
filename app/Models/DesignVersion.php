<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignVersion extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'version_no', 'status',
        'notes', 'submitted_at', 'decided_at', 'reject_reason',
    ];

    // Flutter model reads 'version_number' and 'approved_at'
    protected $appends = ['version_number', 'approved_at'];

    public function getVersionNumberAttribute(): int
    {
        return (int) ($this->attributes['version_no'] ?? 1);
    }

    public function getApprovedAtAttribute(): ?string
    {
        return $this->status === 'approved' ? $this->decided_at?->toISOString() : null;
    }

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
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
