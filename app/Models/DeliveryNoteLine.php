<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteLine extends Model
{
    protected $fillable = ['delivery_note_id', 'product_id', 'description', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2'];
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
