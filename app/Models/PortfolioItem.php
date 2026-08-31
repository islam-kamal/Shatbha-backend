<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioItem extends Model
{
    protected $fillable = ['vendor_account_id', 'title', 'description', 'work_type', 'media_id'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorAccount::class, 'vendor_account_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
