<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLot extends Model
{
    protected $fillable = [
        'place_id',
        'product_id',
        'purchase_id',
        'lot_code',
        'purchased_at',
        'expires_at',
        'unit_cost',
        'qty_initial',
        'qty_remaining',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'date',
        'unit_cost' => 'float',
        'qty_initial' => 'float',
        'qty_remaining' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
