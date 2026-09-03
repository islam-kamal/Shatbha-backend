<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspirationItem extends Model
{
    protected $fillable = [
        'design_board_id',
        'room',
        'category',
        'title',
        'tags',
        'notes',
        'media_id',
    ];

    public function designBoard(): BelongsTo
    {
        return $this->belongsTo(DesignBoard::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
