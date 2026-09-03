<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignBoard extends Model
{
    protected $fillable = ['project_id', 'title', 'style', 'designer_notes'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function inspirationItems(): HasMany
    {
        return $this->hasMany(InspirationItem::class);
    }
}
