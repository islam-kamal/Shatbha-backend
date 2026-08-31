<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryMilestone extends Model
{
    protected $fillable = ['project_id', 'title', 'is_done'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
