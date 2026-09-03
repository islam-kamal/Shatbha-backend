<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRequestComment extends Model
{
    protected $fillable = [
        'project_request_id',
        'author_type',
        'author_id',
        'author_label',
        'body',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProjectRequest::class, 'project_request_id');
    }
}
