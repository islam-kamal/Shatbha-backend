<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignPlanComment extends Model
{
    protected $fillable = [
        'design_plan_id',
        'user_id',
        'client_account_id',
        'author_label',
        'body',
    ];

    public function designPlan(): BelongsTo
    {
        return $this->belongsTo(DesignPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clientAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAccount::class);
    }
}
