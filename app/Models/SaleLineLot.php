<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLineLot extends Model
{
    protected $fillable = [
        'sale_line_id',
        'stock_lot_id',
        'qty_base',
        'qty_base_returned',
        'unit_cost',
    ];

    protected $casts = [
        'qty_base' => 'float',
        'qty_base_returned' => 'float',
        'unit_cost' => 'float',
    ];

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }
}
