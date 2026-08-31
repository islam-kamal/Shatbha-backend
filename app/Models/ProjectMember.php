<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    protected $fillable = ['project_id', 'member_type', 'member_id', 'role'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
