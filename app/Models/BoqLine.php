<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoqLine extends Model
{
    protected $fillable = [
        'project_id',
        'room',
        'trade',
        'description',
        'qty',
        'unit',
        'rate',
        'inspiration_item_id',
        'design_plan_id',
    ];

    protected $appends = ['total'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'rate' => 'decimal:2',
        ];
    }

    public function getTotalAttribute(): string
    {
        return number_format(((float) $this->qty) * ((float) $this->rate), 2, '.', '');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function inspirationItem(): BelongsTo
    {
        return $this->belongsTo(InspirationItem::class);
    }

    public function designPlan(): BelongsTo
    {
        return $this->belongsTo(DesignPlan::class);
    }
}
