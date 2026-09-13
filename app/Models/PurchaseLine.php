<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLine extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'product_presentation_id',
        'stock_lot_id',
        'qty_presentations',
        'factor',
        'qty_base',
        'unit_cost',
    ];

    protected $casts = [
        'qty_presentations' => 'float',
        'factor' => 'float',
        'qty_base' => 'float',
        'unit_cost' => 'float',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id');
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }
}
