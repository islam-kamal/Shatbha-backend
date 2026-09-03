<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    protected $fillable = ['goods_receipt_id', 'po_line_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2'];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PoLine::class);
    }
}
