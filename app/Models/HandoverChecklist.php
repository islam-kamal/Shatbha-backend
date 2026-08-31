<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverChecklist extends Model
{
    protected $fillable = ['project_id', 'item', 'is_checked'];

    protected function casts(): array
    {
        return ['is_checked' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
