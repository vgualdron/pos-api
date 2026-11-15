<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';
    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';
    public const TYPE_SHRINKAGE = 'shrinkage';
    public const TYPE_RECOUNT = 'recount';

    public const TYPES = [
        self::TYPE_ADJUSTMENT_IN,
        self::TYPE_ADJUSTMENT_OUT,
        self::TYPE_SHRINKAGE,
        self::TYPE_RECOUNT,
    ];

    protected $fillable = [
        'place_id',
        'product_id',
        'stock_lot_id',
        'user_id',
        'type',
        'qty_before',
        'qty_delta',
        'qty_after',
        'notes',
    ];

    protected $casts = [
        'qty_before' => 'float',
        'qty_delta' => 'float',
        'qty_after' => 'float',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
