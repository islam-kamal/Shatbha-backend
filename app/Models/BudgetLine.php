<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    protected $fillable = ['project_id', 'category', 'planned', 'committed', 'actual'];

    protected function casts(): array
    {
        return [
            'planned' => 'decimal:2',
            'committed' => 'decimal:2',
            'actual' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
