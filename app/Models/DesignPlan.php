<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignPlan extends Model
{
    protected $fillable = [
        'project_id',
        'type',
        'title',
        'room',
        'version',
        'status',
        'media_id',
        'inspiration_item_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function inspirationItem(): BelongsTo
    {
        return $this->belongsTo(InspirationItem::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DesignPlanComment::class)->orderBy('id');
    }
}
