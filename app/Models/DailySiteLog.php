<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySiteLog extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'log_date', 'workers_count',
        'contractors_text', 'work_completed', 'materials_received',
        'problems', 'photos_json', 'notes', 'tomorrow_plan', 'delay_reason',
    ];

    // Flutter model uses different field names — expose them as appended attributes
    protected $appends = ['workers_on_site', 'summary', 'progress_notes', 'weather_condition'];

    public function getWorkersOnSiteAttribute(): ?int
    {
        return $this->attributes['workers_count'] ?? null;
    }

    public function getSummaryAttribute(): ?string
    {
        return $this->attributes['work_completed'] ?? null;
    }

    public function getProgressNotesAttribute(): ?string
    {
        return $this->attributes['notes'] ?? null;
    }

    public function getWeatherConditionAttribute(): ?string
    {
        // No weather field in DB; return null for now
        return null;
    }

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'workers_count' => 'integer',
            'photos_json' => 'array',
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
